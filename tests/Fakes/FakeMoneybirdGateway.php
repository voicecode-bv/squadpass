<?php

namespace Tests\Fakes;

use App\Enums\MoneybirdInvoiceDelivery;
use App\Models\Club;
use App\Services\Moneybird\MoneybirdGateway;
use Carbon\CarbonInterface;
use RuntimeException;

class FakeMoneybirdGateway implements MoneybirdGateway
{
    /** @var array<int, array{club: int, contact_id: string|null, attributes: array<string, mixed>, id: string}> */
    public array $upsertedContacts = [];

    /** @var array<int, array{club: int, contact_id: string, lines: array<int, array<string, mixed>>, attributes: array<string, mixed>, id: string}> */
    public array $createdInvoices = [];

    /** @var array<int, array{club: int, invoice_id: string, delivery: MoneybirdInvoiceDelivery}> */
    public array $sentInvoices = [];

    /** @var array<int, array{club: int, invoice_id: string, amount_cents: int, paid_at: string}> */
    public array $registeredPayments = [];

    /** @var array<int, array{club: int, invoice_id: string, id: string}> */
    public array $createdCreditInvoices = [];

    /** @var list<string> */
    public array $settledCreditInvoices = [];

    /** @var list<array{id: string, name: string}> */
    public array $administrations = [['id' => '123456', 'name' => 'Demo Administratie']];

    /** @var list<string> tokens that are rejected by listAdministrations */
    public array $invalidTokens = [];

    protected int $sequence = 0;

    public function listAdministrations(string $accessToken): array
    {
        if (in_array($accessToken, $this->invalidTokens, true)) {
            throw new RuntimeException('HTTP request returned status code 401');
        }

        return $this->administrations;
    }

    public function listRevenueLedgerAccounts(Club $club): array
    {
        return [['id' => 'la_1', 'name' => 'Omzet lessen'], ['id' => 'la_2', 'name' => 'Omzet abonnementen']];
    }

    public function listSalesTaxRates(Club $club): array
    {
        return [['id' => 'tr_21', 'name' => '21% BTW', 'percentage' => '21.0'], ['id' => 'tr_9', 'name' => '9% BTW', 'percentage' => '9.0']];
    }

    public function listInvoiceWorkflows(Club $club): array
    {
        return [['id' => 'wf_1', 'name' => 'Standaard workflow']];
    }

    public function listDocumentStyles(Club $club): array
    {
        return [['id' => 'ds_1', 'name' => 'Standaard']];
    }

    public function upsertContact(Club $club, ?string $contactId, array $attributes): string
    {
        $id = $contactId ?? 'mb_contact_'.(++$this->sequence);

        $this->upsertedContacts[] = ['club' => $club->id, 'contact_id' => $contactId, 'attributes' => $attributes, 'id' => $id];

        return $id;
    }

    public function createInvoice(Club $club, string $contactId, array $lines, array $attributes = []): string
    {
        $id = 'mb_invoice_'.(++$this->sequence);

        $this->createdInvoices[] = ['club' => $club->id, 'contact_id' => $contactId, 'lines' => $lines, 'attributes' => $attributes, 'id' => $id];

        return $id;
    }

    public function sendInvoice(Club $club, string $invoiceId, MoneybirdInvoiceDelivery $delivery): void
    {
        $this->sentInvoices[] = ['club' => $club->id, 'invoice_id' => $invoiceId, 'delivery' => $delivery];
    }

    public function registerPayment(Club $club, string $invoiceId, int $amountCents, CarbonInterface $paidAt): void
    {
        $this->registeredPayments[] = ['club' => $club->id, 'invoice_id' => $invoiceId, 'amount_cents' => $amountCents, 'paid_at' => $paidAt->toDateString()];
    }

    public function createCreditInvoice(Club $club, string $invoiceId): string
    {
        $id = 'mb_credit_'.(++$this->sequence);

        $this->createdCreditInvoices[] = ['club' => $club->id, 'invoice_id' => $invoiceId, 'id' => $id];

        return $id;
    }

    public function settleCreditInvoice(Club $club, string $creditInvoiceId): void
    {
        $this->settledCreditInvoices[] = $creditInvoiceId;
    }
}
