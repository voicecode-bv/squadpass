<?php

namespace App\Actions\Moneybird;

use App\Enums\MoneybirdInvoiceState;
use App\Enums\PaymentStatus;
use App\Models\MoneybirdInvoice;
use App\Models\Payment;
use App\Services\Moneybird\MoneybirdGateway;

class CreateCreditInvoiceForPayment
{
    public function __construct(
        protected MoneybirdGateway $gateway,
        protected RecordInvoice $recordInvoice,
    ) {}

    /**
     * Reverse the Moneybird invoice of a refunded payment with a credit invoice.
     * With a finalizing delivery setting the credit invoice is also sent and
     * settled against the original. Idempotent via payments.moneybird_credit_invoice_id;
     * a credit invoice that was created but not yet finalized or mirrored resumes where it stopped.
     */
    public function handle(Payment $payment): ?string
    {
        $club = $payment->club;

        if (! $club->hasMoneybirdConfigured()
            || $payment->status !== PaymentStatus::Refunded
            || $payment->moneybird_invoice_id === null) {
            return null;
        }

        if ($payment->moneybird_credit_invoice_id !== null) {
            // The credit invoice exists; only a run that stopped before mirroring it still has work left.
            if (MoneybirdInvoice::isRecordedFor($payment, $payment->moneybird_credit_invoice_id)) {
                return null;
            }

            $creditInvoiceId = $payment->moneybird_credit_invoice_id;
            $state = MoneybirdInvoiceState::fromApi($this->gateway->getInvoice($club, $creditInvoiceId)['state'] ?? null);
        } else {
            $creditInvoiceId = $this->gateway->createCreditInvoice($club, $payment->moneybird_invoice_id);

            $payment->update(['moneybird_credit_invoice_id' => $creditInvoiceId]);

            $state = MoneybirdInvoiceState::Draft;
        }

        if ($club->moneybird_invoice_delivery->finalizes()) {
            if ($state === MoneybirdInvoiceState::Draft) {
                $this->gateway->sendInvoice($club, $creditInvoiceId, $club->moneybird_invoice_delivery);
                $state = MoneybirdInvoiceState::Open;
            }

            if ($state->awaitsPayment()) {
                $this->gateway->settleCreditInvoice($club, $creditInvoiceId);
            }
        }

        // Mirror the credit invoice locally so it shows up in the club's overview.
        $this->recordInvoice->handle($club, $this->gateway->getInvoice($club, $creditInvoiceId), $payment);

        $club->recordMoneybirdSuccess();

        return $creditInvoiceId;
    }
}
