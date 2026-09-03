<?php

namespace App\Actions\Moneybird;

use App\Enums\PaymentStatus;
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
     * settled against the original. Idempotent via payments.moneybird_credit_invoice_id.
     */
    public function handle(Payment $payment): ?string
    {
        $club = $payment->club;

        if (! $club->hasMoneybirdConfigured()
            || $payment->status !== PaymentStatus::Refunded
            || $payment->moneybird_invoice_id === null
            || $payment->moneybird_credit_invoice_id !== null) {
            return null;
        }

        $creditInvoiceId = $this->gateway->createCreditInvoice($club, $payment->moneybird_invoice_id);

        $payment->update(['moneybird_credit_invoice_id' => $creditInvoiceId]);

        if ($club->moneybird_invoice_delivery->finalizes()) {
            $this->gateway->sendInvoice($club, $creditInvoiceId, $club->moneybird_invoice_delivery);
            $this->gateway->settleCreditInvoice($club, $creditInvoiceId);
        }

        // Mirror the credit invoice locally so it shows up in the club's overview.
        $this->recordInvoice->handle($club, $this->gateway->getInvoice($club, $creditInvoiceId), $payment);

        $club->recordMoneybirdSuccess();

        return $creditInvoiceId;
    }
}
