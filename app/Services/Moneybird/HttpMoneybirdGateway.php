<?php

namespace App\Services\Moneybird;

use App\Enums\MoneybirdInvoiceDelivery;
use App\Models\Club;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class HttpMoneybirdGateway implements MoneybirdGateway
{
    protected const string BASE_URL = 'https://moneybird.com/api/v2/';

    public function listAdministrations(string $accessToken): array
    {
        $administrations = Http::baseUrl(self::BASE_URL)
            ->withToken($accessToken)
            ->acceptJson()
            ->get('administrations.json')
            ->throw()
            ->json();

        return collect($administrations)
            ->map(fn (array $administration) => ['id' => (string) $administration['id'], 'name' => (string) $administration['name']])
            ->values()
            ->all();
    }

    public function listRevenueLedgerAccounts(Club $club): array
    {
        return collect($this->clientFor($club)->get('ledger_accounts.json')->throw()->json())
            ->filter(fn (array $account) => ($account['account_type'] ?? null) === 'revenue' && ($account['active'] ?? true))
            ->map(fn (array $account) => ['id' => (string) $account['id'], 'name' => (string) $account['name']])
            ->values()
            ->all();
    }

    public function listSalesTaxRates(Club $club): array
    {
        return collect($this->clientFor($club)->get('tax_rates.json', ['filter' => 'tax_rate_type:sales_invoice', 'per_page' => 100])->throw()->json())
            ->filter(fn (array $rate) => $rate['active'] ?? true)
            ->map(fn (array $rate) => [
                'id' => (string) $rate['id'],
                'name' => (string) $rate['name'],
                'percentage' => (string) ($rate['percentage'] ?? ''),
            ])
            ->values()
            ->all();
    }

    public function listInvoiceWorkflows(Club $club): array
    {
        return collect($this->clientFor($club)->get('workflows.json')->throw()->json())
            ->filter(fn (array $workflow) => ($workflow['type'] ?? null) === 'InvoiceWorkflow' && ($workflow['active'] ?? true))
            ->map(fn (array $workflow) => ['id' => (string) $workflow['id'], 'name' => (string) $workflow['name']])
            ->values()
            ->all();
    }

    public function listDocumentStyles(Club $club): array
    {
        return collect($this->clientFor($club)->get('document_styles.json')->throw()->json())
            ->map(fn (array $style) => ['id' => (string) $style['id'], 'name' => (string) $style['name']])
            ->values()
            ->all();
    }

    public function upsertContact(Club $club, ?string $contactId, array $attributes): string
    {
        $client = $this->clientFor($club);

        $response = $contactId !== null
            ? $client->patch("contacts/{$contactId}.json", ['contact' => $attributes])
            : $client->post('contacts.json', ['contact' => $attributes]);

        return (string) $response->throw()->json('id');
    }

    public function createInvoice(Club $club, string $contactId, array $lines, array $attributes = []): string
    {
        $response = $this->clientFor($club)->post('sales_invoices.json', [
            'sales_invoice' => array_merge($attributes, [
                'contact_id' => $contactId,
                'details_attributes' => collect($lines)->map(fn (array $line) => array_filter([
                    'description' => $line['description'],
                    'price' => number_format($line['price_cents'] / 100, 2, '.', ''),
                    'amount' => '1',
                    'tax_rate_id' => $line['tax_rate_id'] ?? null,
                    'ledger_account_id' => $line['ledger_account_id'] ?? null,
                ], fn ($value) => $value !== null))->all(),
            ]),
        ]);

        return (string) $response->throw()->json('id');
    }

    public function sendInvoice(Club $club, string $invoiceId, MoneybirdInvoiceDelivery $delivery): void
    {
        $this->clientFor($club)->patch("sales_invoices/{$invoiceId}/send_invoice.json", [
            'sales_invoice_sending' => ['delivery_method' => $delivery->moneybirdDeliveryMethod()],
        ])->throw();
    }

    public function registerPayment(Club $club, string $invoiceId, int $amountCents, CarbonInterface $paidAt): void
    {
        $this->clientFor($club)->post("sales_invoices/{$invoiceId}/payments.json", [
            'payment' => [
                'payment_date' => $paidAt->toDateString(),
                'price' => number_format($amountCents / 100, 2, '.', ''),
            ],
        ])->throw();
    }

    public function createCreditInvoice(Club $club, string $invoiceId): string
    {
        return (string) $this->clientFor($club)
            ->patch("sales_invoices/{$invoiceId}/duplicate_creditinvoice.json", [])
            ->throw()
            ->json('id');
    }

    public function settleCreditInvoice(Club $club, string $creditInvoiceId): void
    {
        $this->clientFor($club)
            ->patch("sales_invoices/{$creditInvoiceId}/register_payment_creditinvoice.json", [])
            ->throw();
    }

    protected function clientFor(Club $club): PendingRequest
    {
        if (! $club->hasMoneybirdConfigured()) {
            throw new InvalidArgumentException("Club [{$club->id}] has no Moneybird administration configured.");
        }

        return Http::baseUrl(self::BASE_URL."{$club->moneybird_administration_id}/")
            ->withToken($club->moneybird_access_token)
            ->acceptJson();
    }
}
