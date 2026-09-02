<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Mollie\MollieGateway;

class StartSubscription
{
    public function __construct(
        protected MollieGateway $gateway,
        protected ResolveMollieCustomer $resolveMollieCustomer,
    ) {}

    /**
     * Start a subscription: the first payment establishes the Mollie mandate
     * and covers the first billing period. Returns the checkout URL.
     */
    public function handle(User $user, SubscriptionPlan $plan): string
    {
        $club = $plan->club;

        throw_unless($user->isMemberOf($club), PaymentException::membershipRequired());
        throw_unless($club->mollie_api_key, PaymentException::onlinePaymentsUnavailable());

        $blocking = Subscription::query()
            ->where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->get()
            ->first(fn (Subscription $subscription) => $subscription->blocksNewSubscription());

        throw_if($blocking, PaymentException::subscriptionAlreadyExists());

        $customerId = $this->resolveMollieCustomer->handle($club, $user);

        $subscription = Subscription::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Pending,
            'starts_at' => today(),
        ]);

        $result = $this->gateway->createPayment(
            club: $club,
            amountCents: $plan->price_cents,
            description: "{$club->name} — {$plan->name} ({$plan->interval->label()})",
            redirectUrl: route('portal.subscriptions'),
            webhookUrl: route('webhooks.mollie', $club),
            customerId: $customerId,
            sequenceType: 'first',
            metadata: [
                'purpose' => 'subscription_first',
                'subscription_id' => $subscription->id,
            ],
        );

        Payment::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'payable_type' => Subscription::class,
            'payable_id' => $subscription->id,
            'mollie_payment_id' => $result->id,
            'status' => PaymentStatus::Open,
            'amount_cents' => $plan->price_cents,
            'currency' => $club->currency,
            'metadata' => ['purpose' => 'subscription_first'],
        ]);

        return $result->checkoutUrl ?? route('portal.subscriptions');
    }
}
