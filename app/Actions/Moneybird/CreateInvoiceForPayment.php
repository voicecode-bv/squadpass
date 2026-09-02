<?php

namespace App\Actions\Moneybird;

use App\Enums\PaymentStatus;
use App\Models\Membership;
use App\Models\Payment;
use App\Services\Moneybird\MoneybirdGateway;

class CreateInvoiceForPayment
{
    public function __construct(
        protected MoneybirdGateway $gateway,
        protected SyncMemberContact $syncMemberContact,
    ) {}

    /**
     * Create a draft sales invoice in Moneybird for a paid payment. Idempotent:
     * a payment that already has an invoice, is not paid, or belongs to a club
     * without Moneybird is skipped.
     */
    public function handle(Payment $payment): ?string
    {
        $club = $payment->club;

        if (! $club->hasMoneybirdConfigured()
            || $payment->status !== PaymentStatus::Paid
            || $payment->moneybird_invoice_id !== null) {
            return null;
        }

        $membership = Membership::query()
            ->where('club_id', $club->id)
            ->where('user_id', $payment->user_id)
            ->first();

        if (! $membership) {
            return null;
        }

        $contactId = $membership->moneybird_contact_id ?? $this->syncMemberContact->handle($membership);

        if ($contactId === null) {
            return null;
        }

        $invoiceId = $this->gateway->createInvoice($club, $contactId, [
            [
                'description' => $this->descriptionFor($payment),
                'price_cents' => $payment->amount_cents,
            ],
        ]);

        $payment->update(['moneybird_invoice_id' => $invoiceId]);

        return $invoiceId;
    }

    protected function descriptionFor(Payment $payment): string
    {
        $label = match ($payment->purpose()) {
            'strip_card' => __('Strip card'),
            'subscription_first', 'subscription_recurring' => __('Subscription'),
            default => __('Payment'),
        };

        return "{$label} - {$payment->club->name}";
    }
}
