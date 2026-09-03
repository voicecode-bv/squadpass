<?php

namespace App\Actions\Moneybird;

use App\Enums\PaymentStatus;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\StripCardPlan;
use App\Models\Subscription;
use App\Services\Moneybird\MoneybirdGateway;

class CreateInvoiceForPayment
{
    public function __construct(
        protected MoneybirdGateway $gateway,
        protected SyncMemberContact $syncMemberContact,
        protected RecordInvoice $recordInvoice,
    ) {}

    /**
     * Create a sales invoice in Moneybird for a paid payment, using the club's
     * ledger account, tax rate, workflow and document style. Depending on the
     * club's delivery setting the invoice stays a draft, or is finalized and
     * the Mollie payment is registered on it. Idempotent: a payment that already
     * has an invoice, is not paid, or belongs to a club without Moneybird is skipped.
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
                'tax_rate_id' => $club->moneybird_tax_rate_id,
                'ledger_account_id' => $club->moneybird_ledger_account_id,
            ],
        ], array_filter([
            'reference' => "SquadPass #{$payment->id}",
            'invoice_date' => ($payment->paid_at ?? now())->toDateString(),
            'prices_are_incl_tax' => true,
            'workflow_id' => $club->moneybird_workflow_id,
            'document_style_id' => $club->moneybird_document_style_id,
        ], fn ($value) => $value !== null));

        // Persist the id before finalizing, so a failure later never creates a second invoice.
        $payment->update(['moneybird_invoice_id' => $invoiceId]);

        if ($club->moneybird_invoice_delivery->finalizes()) {
            $this->gateway->sendInvoice($club, $invoiceId, $club->moneybird_invoice_delivery);
            $this->gateway->registerPayment($club, $invoiceId, $payment->amount_cents, $payment->paid_at ?? now());
        }

        // Mirror the invoice locally so it shows up in the club's overview.
        $this->recordInvoice->handle($club, $this->gateway->getInvoice($club, $invoiceId), $payment, [$contactId => $membership->id]);

        $club->recordMoneybirdSuccess();

        return $invoiceId;
    }

    /**
     * Invoice line text: what was bought, with the plan name when known.
     */
    protected function descriptionFor(Payment $payment): string
    {
        $payable = $payment->payable;

        return match (true) {
            $payable instanceof StripCardPlan => __('Strip card :name', ['name' => $payable->name]),
            $payable instanceof Subscription => __('Subscription :name (:interval)', [
                'name' => $payable->plan->name,
                'interval' => strtolower($payable->plan->interval->label()),
            ]),
            default => match ($payment->purpose()) {
                'strip_card' => __('Strip card'),
                'subscription_first', 'subscription_recurring' => __('Subscription'),
                default => __('Payment'),
            },
        }.' - '.$payment->club->name;
    }
}
