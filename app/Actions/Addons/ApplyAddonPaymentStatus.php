<?php

namespace App\Actions\Addons;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AddonPayment;
use App\Models\AddonSubscription;
use App\Services\Mollie\MolliePaymentResult;
use Illuminate\Support\Facades\DB;

class ApplyAddonPaymentStatus
{
    /**
     * Apply a Mollie payment state idempotently: statuses only move forward,
     * and the add-on is activated exactly once when the first payment is paid.
     */
    public function handle(AddonPayment $payment, MolliePaymentResult $result): void
    {
        DB::transaction(function () use ($payment, $result): void {
            $payment = AddonPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($result->status === PaymentStatus::Open) {
                return;
            }

            if ($payment->status->isFinal()) {
                if ($payment->status === PaymentStatus::Paid && $result->status === PaymentStatus::Refunded) {
                    $payment->update(['status' => PaymentStatus::Refunded]);
                }

                return;
            }

            $payment->update([
                'status' => $result->status,
                'paid_at' => $result->status === PaymentStatus::Paid ? now() : null,
            ]);

            $subscription = AddonSubscription::query()->whereKey($payment->addon_subscription_id)->lockForUpdate()->first();

            if (! $subscription) {
                return;
            }

            if ($result->status === PaymentStatus::Paid) {
                $this->fulfill($subscription, $payment, $result);
            } else {
                $this->handleFailure($subscription, $payment);
            }
        });
    }

    protected function fulfill(AddonSubscription $subscription, AddonPayment $payment, MolliePaymentResult $result): void
    {
        if ($payment->purpose === AddonPayment::PURPOSE_FIRST) {
            if ($subscription->status !== SubscriptionStatus::Pending) {
                return;
            }

            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'mollie_mandate_id' => $result->mandateId,
                'next_billing_at' => $subscription->starts_at->addMonthNoOverflow(),
            ]);

            return;
        }

        if ($subscription->status === SubscriptionStatus::PastDue) {
            $subscription->update(['status' => SubscriptionStatus::Active]);
        }
    }

    protected function handleFailure(AddonSubscription $subscription, AddonPayment $payment): void
    {
        if ($payment->purpose === AddonPayment::PURPOSE_FIRST) {
            // No mandate was established; drop the pending subscription so the club can retry.
            if ($subscription->status === SubscriptionStatus::Pending) {
                $subscription->delete();
            }

            return;
        }

        if ($subscription->status === SubscriptionStatus::Active) {
            $subscription->update(['status' => SubscriptionStatus::PastDue]);
        }
    }
}
