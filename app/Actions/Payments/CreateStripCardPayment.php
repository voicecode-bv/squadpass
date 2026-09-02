<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Models\StripCardPlan;
use App\Models\User;
use App\Services\Mollie\MollieGateway;

class CreateStripCardPayment
{
    public function __construct(
        protected MollieGateway $gateway,
        protected ResolveMollieCustomer $resolveMollieCustomer,
    ) {}

    /**
     * Start a Mollie checkout for a strip card and return the checkout URL.
     */
    public function handle(User $user, StripCardPlan $plan): string
    {
        $club = $plan->club;

        throw_unless($user->isMemberOf($club), PaymentException::membershipRequired());
        throw_unless($club->acceptsOnlinePayments(), PaymentException::onlinePaymentsUnavailable());

        $customerId = $this->resolveMollieCustomer->handle($club, $user);

        $result = $this->gateway->createPayment(
            account: $club,
            amountCents: $plan->price_cents,
            description: "{$club->name} — {$plan->name}",
            redirectUrl: route('portal.cards'),
            webhookUrl: route('webhooks.mollie', $club),
            customerId: $customerId,
            metadata: [
                'purpose' => 'strip_card',
                'strip_card_plan_id' => $plan->id,
                'user_id' => $user->id,
            ],
        );

        Payment::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'payable_type' => StripCardPlan::class,
            'payable_id' => $plan->id,
            'mollie_payment_id' => $result->id,
            'status' => PaymentStatus::Open,
            'amount_cents' => $plan->price_cents,
            'currency' => $club->currency,
            'metadata' => ['purpose' => 'strip_card'],
        ]);

        return $result->checkoutUrl ?? route('portal.cards');
    }
}
