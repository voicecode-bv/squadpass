<?php

namespace App\Actions\Moneybird;

use App\Exceptions\MoneybirdException;
use App\Models\MoneybirdInvoice;
use App\Services\Moneybird\MoneybirdGateway;

class CreateCreditInvoice
{
    public function __construct(protected MoneybirdGateway $gateway, protected RecordInvoice $recordInvoice) {}

    /**
     * Duplicate an invoice into a draft credit invoice in Moneybird and mirror
     * it locally, linked to the invoice it credits.
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

        $payload = $this->gateway->createCreditInvoice($club, $invoice->moneybird_id);

        $creditInvoice = $this->recordInvoice->handle($club, $payload);

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

        return $creditInvoice;
    }
}
