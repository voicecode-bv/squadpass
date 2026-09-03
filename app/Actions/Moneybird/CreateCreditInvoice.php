<?php

namespace App\Actions\Moneybird;

use App\Exceptions\MoneybirdException;
use App\Models\MoneybirdInvoice;
use App\Services\Moneybird\MoneybirdGateway;

class CreateCreditInvoice
{
    public function __construct(
        protected MoneybirdGateway $gateway,
        protected RecordInvoice $recordInvoice,
    ) {}

    /**
     * Turn an invoice from the overview into a credit invoice in Moneybird and
     * mirror it locally, linked to the invoice it credits. With a finalizing
     * delivery setting the credit invoice is also sent and settled against the
     * original, exactly like the refund driven CreateCreditInvoiceForPayment.
     *
     * @throws MoneybirdException
     */
    public function handle(MoneybirdInvoice $invoice): MoneybirdInvoice
    {
        $club = $invoice->club;

        if (! $club->hasMoneybirdConfigured()) {
            throw MoneybirdException::notConfigured();
        }

        if ($invoice->is_credit_invoice) {
            throw MoneybirdException::cannotCreditCreditInvoice();
        }

        if (! $invoice->isCreditable()) {
            throw MoneybirdException::alreadyCredited();
        }

        $creditInvoiceId = $this->gateway->createCreditInvoice($club, $invoice->moneybird_id);

        if ($club->moneybird_invoice_delivery->finalizes()) {
            $this->gateway->sendInvoice($club, $creditInvoiceId, $club->moneybird_invoice_delivery);
            $this->gateway->settleCreditInvoice($club, $creditInvoiceId);
        }

        $creditInvoice = $this->recordInvoice->handle($club, $this->gateway->getInvoice($club, $creditInvoiceId), $invoice->payment);

        // Moneybird links the duplicate itself, but never leave the local
        // mirror without the link, or without the member, when it does not.
        $creditInvoice->fill([
            'credited_invoice_id' => $invoice->id,
            'is_credit_invoice' => true,
            'membership_id' => $creditInvoice->membership_id ?? $invoice->membership_id,
        ]);

        if ($creditInvoice->isDirty()) {
            $creditInvoice->save();
        }

        $club->recordMoneybirdSuccess();

        return $creditInvoice;
    }
}
