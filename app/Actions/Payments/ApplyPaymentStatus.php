<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Jobs\CreateMoneybirdCreditInvoice;
use App\Jobs\CreateMoneybirdInvoice;
use App\Models\Payment;
use App\Models\StripCard;
use App\Models\StripCardPlan;
use App\Models\Subscription;
use App\Services\Mollie\MolliePaymentResult;
use Illuminate\Support\Facades\DB;

class ApplyPaymentStatus
{
    /**
     * Apply a Mollie payment state idempotently: statuses only move forward,
     * and fulfillment runs exactly once when a payment becomes paid.
     */
    public function handle(Payment $payment, MolliePaymentResult $result): void
    {
        DB::transaction(function () use ($payment, $result): void {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($result->status === PaymentStatus::Open) {
                return;
            }

            if ($payment->status->isFinal()) {
                // Only a refund can follow a paid payment.
                if ($payment->status === PaymentStatus::Paid && $result->status === PaymentStatus::Refunded) {
                    $payment->update(['status' => PaymentStatus::Refunded]);

                    // Reverse the Moneybird invoice with a credit invoice once the transaction commits.
                    CreateMoneybirdCreditInvoice::dispatch($payment)->afterCommit();
                }

                return;
            }

            $payment->update([
                'status' => $result->status,
                'paid_at' => $result->status === PaymentStatus::Paid ? now() : null,
            ]);

            if ($result->status === PaymentStatus::Paid) {
                $this->fulfill($payment, $result);

                // Invoice the paid payment in Moneybird once the transaction commits.
                CreateMoneybirdInvoice::dispatch($payment)->afterCommit();
            } else {
                $this->handleFailure($payment);
            }
        });
    }

    protected function fulfill(Payment $payment, MolliePaymentResult $result): void
    {
        match ($payment->purpose()) {
            'strip_card' => $this->fulfillStripCard($payment),
            'subscription_first' => $this->activateSubscription($payment, $result),
            'subscription_recurring' => $this->recoverSubscription($payment),
            default => null,
        };
    }

    protected function handleFailure(Payment $payment): void
    {
        match ($payment->purpose()) {
            // A failed first payment never established a mandate; drop the
            // pending subscription so the member can try again.
            'subscription_first' => $this->abandonPendingSubscription($payment),
            'subscription_recurring' => $this->markSubscriptionPastDue($payment),
            default => null,
        };
    }

    protected function activateSubscription(Payment $payment, MolliePaymentResult $result): void
    {
        /** @var Subscription $subscription */
        $subscription = $payment->payable;

        if ($subscription->status !== SubscriptionStatus::Pending) {
            return;
        }

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'mollie_mandate_id' => $result->mandateId,
            'next_billing_at' => $subscription->plan->interval->addTo($subscription->starts_at),
            'credits_remaining' => $subscription->plan->credits,
        ]);
    }

    protected function recoverSubscription(Payment $payment): void
    {
        /** @var Subscription $subscription */
        $subscription = $payment->payable;

        if ($subscription->status === SubscriptionStatus::PastDue) {
            $subscription->update(['status' => SubscriptionStatus::Active]);
        }
    }

    protected function abandonPendingSubscription(Payment $payment): void
    {
        /** @var Subscription|null $subscription */
        $subscription = $payment->payable;

        if ($subscription && $subscription->status === SubscriptionStatus::Pending) {
            $subscription->delete();
        }
    }

    protected function markSubscriptionPastDue(Payment $payment): void
    {
        /** @var Subscription|null $subscription */
        $subscription = $payment->payable;

        if ($subscription && $subscription->status === SubscriptionStatus::Active) {
            $subscription->update(['status' => SubscriptionStatus::PastDue]);
        }
    }

    protected function fulfillStripCard(Payment $payment): void
    {
        $alreadyFulfilled = StripCard::query()->where('payment_id', $payment->id)->exists();

        if ($alreadyFulfilled) {
            return;
        }

        /** @var StripCardPlan $plan */
        $plan = $payment->payable;

        StripCard::create([
            'club_id' => $payment->club_id,
            'user_id' => $payment->user_id,
            'strip_card_plan_id' => $plan->id,
            'payment_id' => $payment->id,
            'strips_total' => $plan->strips,
            'strips_remaining' => $plan->strips,
            'purchased_at' => today(),
            'expires_at' => $plan->validity_months ? today()->addMonths($plan->validity_months) : null,
        ]);
    }
}
