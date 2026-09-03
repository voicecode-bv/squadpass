<?php

namespace App\Services\Moneybird;

use App\Models\Club;
use Illuminate\Support\Carbon;

interface MoneybirdGateway
{
    /**
     * Create or update a contact in the club's Moneybird administration and
     * return its Moneybird contact id.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upsertContact(Club $club, ?string $contactId, array $attributes): string;

    /**
     * Create a draft sales invoice for a contact and return the created invoice.
     *
     * @param  list<array{description: string, price_cents: int}>  $lines
     * @return array<string, mixed>
     */
    public function createInvoice(Club $club, string $contactId, array $lines): array;

    /**
     * Fetch a single sales invoice.
     *
     * @return array<string, mixed>
     */
    public function getInvoice(Club $club, string $invoiceId): array;

    /**
     * Fetch one page of sales invoices, newest changes first. Passing
     * $updatedAfter limits the page to invoices changed since that moment,
     * which keeps repeated syncs cheap.
     *
     * @return list<array<string, mixed>>
     */
    public function listInvoices(Club $club, ?Carbon $updatedAfter = null, int $page = 1, int $perPage = 100): array;

    /**
     * Duplicate a sales invoice into a draft credit invoice and return it.
     *
     * @return array<string, mixed>
     */
    public function createCreditInvoice(Club $club, string $invoiceId): array;
}
