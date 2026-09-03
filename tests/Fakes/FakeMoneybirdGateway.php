<?php

namespace Tests\Fakes;

use App\Models\Club;
use App\Services\Moneybird\MoneybirdGateway;
use Illuminate\Support\Carbon;

class FakeMoneybirdGateway implements MoneybirdGateway
{
    /** @var array<int, array{club: int, contact_id: string|null, attributes: array<string, mixed>, id: string}> */
    public array $upsertedContacts = [];

    /** @var array<int, array{club: int, contact_id: string, lines: array<int, array{description: string, price_cents: int}>, id: string}> */
    public array $createdInvoices = [];

    /** @var array<int, array{club: int, invoice_id: string, id: string}> */
    public array $createdCreditInvoices = [];

    /** @var array<string, array<string, mixed>> */
    public array $invoices = [];

    /** @var array<int, array{club: int, updated_after: ?string, page: int}> */
    public array $listCalls = [];

    protected int $sequence = 0;

    public function upsertContact(Club $club, ?string $contactId, array $attributes): string
    {
        $id = $contactId ?? 'mb_contact_'.(++$this->sequence);

        $this->upsertedContacts[] = ['club' => $club->id, 'contact_id' => $contactId, 'attributes' => $attributes, 'id' => $id];

        return $id;
    }

    public function createInvoice(Club $club, string $contactId, array $lines): array
    {
        $id = 'mb_invoice_'.(++$this->sequence);

        $this->createdInvoices[] = ['club' => $club->id, 'contact_id' => $contactId, 'lines' => $lines, 'id' => $id];

        $totalCents = collect($lines)->sum('price_cents');

        return $this->invoices[$id] = [
            'id' => $id,
            'contact_id' => $contactId,
            'invoice_id' => null,
            'state' => 'draft',
            'invoice_date' => today()->toDateString(),
            'currency' => 'EUR',
            'total_price_incl_tax' => number_format($totalCents / 100, 2, '.', ''),
            'updated_at' => now()->toIso8601String(),
            'details' => collect($lines)->map(fn (array $line) => [
                'description' => $line['description'],
                'amount' => '1',
                'price' => number_format($line['price_cents'] / 100, 2, '.', ''),
                'total_price_excl_tax_with_discount' => number_format($line['price_cents'] / 100, 2, '.', ''),
            ])->all(),
        ];
    }

    public function getInvoice(Club $club, string $invoiceId): array
    {
        return $this->invoices[$invoiceId] ?? ['id' => $invoiceId];
    }

    public function listInvoices(Club $club, ?Carbon $updatedAfter = null, int $page = 1, int $perPage = 100): array
    {
        $this->listCalls[] = ['club' => $club->id, 'updated_after' => $updatedAfter?->toIso8601String(), 'page' => $page];

        return $page === 1 ? array_values($this->invoices) : [];
    }

    public function createCreditInvoice(Club $club, string $invoiceId): array
    {
        $id = 'mb_credit_'.(++$this->sequence);
        $original = $this->invoices[$invoiceId] ?? [];

        $this->createdCreditInvoices[] = ['club' => $club->id, 'invoice_id' => $invoiceId, 'id' => $id];

        return $this->invoices[$id] = array_merge($original, [
            'id' => $id,
            'invoice_id' => null,
            'state' => 'draft',
            'original_sales_invoice_id' => $invoiceId,
            'invoice_date' => today()->toDateString(),
            'updated_at' => now()->toIso8601String(),
            'total_price_incl_tax' => number_format(-1 * (float) ($original['total_price_incl_tax'] ?? 0), 2, '.', ''),
            'details' => collect($original['details'] ?? [])->map(fn (array $detail) => array_merge($detail, [
                'price' => number_format(-1 * (float) $detail['price'], 2, '.', ''),
                'total_price_excl_tax_with_discount' => number_format(-1 * (float) $detail['total_price_excl_tax_with_discount'], 2, '.', ''),
            ]))->all(),
        ]);
    }
}
