<?php

namespace App\Services\Moneybird;

use App\Enums\MoneybirdInvoiceDelivery;
use App\Models\Club;
use Carbon\CarbonInterface;

interface MoneybirdGateway
{
    /**
     * Administrations the given personal API token has access to.
     *
     * @return list<array{id: string, name: string}>
     */
    public function listAdministrations(string $accessToken): array;

    /**
     * Revenue ledger accounts of the club's administration.
     *
     * @return list<array{id: string, name: string}>
     */
    public function listRevenueLedgerAccounts(Club $club): array;

    /**
     * Active sales invoice tax rates of the club's administration.
     *
     * @return list<array{id: string, name: string, percentage: string}>
     */
    public function listSalesTaxRates(Club $club): array;

    /**
     * Invoice workflows of the club's administration.
     *
     * @return list<array{id: string, name: string}>
     */
    public function listInvoiceWorkflows(Club $club): array;

    /**
     * Document styles (layouts) of the club's administration.
     *
     * @return list<array{id: string, name: string}>
     */
    public function listDocumentStyles(Club $club): array;

    /**
     * Create or update a contact in the club's Moneybird administration and
     * return its Moneybird contact id.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upsertContact(Club $club, ?string $contactId, array $attributes): string;

    /**
     * Create a draft sales invoice for a contact and return its Moneybird id.
     *
     * @param  list<array{description: string, price_cents: int, tax_rate_id?: string|null, ledger_account_id?: string|null}>  $lines
     * @param  array<string, mixed>  $attributes  extra sales_invoice attributes (reference, invoice_date, workflow_id, ...)
     */
    public function createInvoice(Club $club, string $contactId, array $lines, array $attributes = []): string;

    /**
     * Finalize a draft invoice, emailing it or marking it as sent manually.
     */
    public function sendInvoice(Club $club, string $invoiceId, MoneybirdInvoiceDelivery $delivery): void;

    /**
     * Register a received payment on a finalized invoice.
     */
    public function registerPayment(Club $club, string $invoiceId, int $amountCents, CarbonInterface $paidAt): void;

    /**
     * Fetch a single sales invoice.
     *
     * @return array<string, mixed>
     */
    public function getInvoice(Club $club, string $invoiceId): array;

    /**
     * Fetch one page of sales invoices. Passing $updatedAfter limits the page
     * to invoices changed since that moment, which keeps repeated syncs cheap.
     *
     * @return list<array<string, mixed>>
     */
    public function listInvoices(Club $club, ?CarbonInterface $updatedAfter = null, int $page = 1, int $perPage = 100): array;

    /**
     * Create a credit invoice that reverses the given invoice and return its id.
     */
    public function createCreditInvoice(Club $club, string $invoiceId): string;

    /**
     * Settle a finalized credit invoice against the invoice it credits.
     */
    public function settleCreditInvoice(Club $club, string $creditInvoiceId): void;
}
