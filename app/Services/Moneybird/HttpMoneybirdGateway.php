<?php

namespace App\Services\Moneybird;

use App\Enums\MoneybirdInvoiceDelivery;
use App\Models\Club;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class HttpMoneybirdGateway implements MoneybirdGateway
{
    protected const string BASE_URL = 'https://moneybird.com/api/v2/';

    public function listAdministrations(string $accessToken): array
    {
        $response = Http::baseUrl(self::BASE_URL)
            ->withToken($accessToken)
            ->acceptJson()
            ->get('administrations.json');

        return array_map(
            fn (array $administration) => ['id' => (string) $administration['id'], 'name' => (string) $administration['name']],
            $this->records($response),
        );
    }

    public function listRevenueLedgerAccounts(Club $club): array
    {
        return array_map(
            fn (array $account) => ['id' => (string) $account['id'], 'name' => (string) $account['name']],
            $this->records(
                $this->clientFor($club)->get('ledger_accounts.json'),
                fn (array $account) => ($account['account_type'] ?? null) === 'revenue' && ($account['active'] ?? true),
            ),
        );
    }

    public function listSalesTaxRates(Club $club): array
    {
        return array_map(
            fn (array $rate) => [
                'id' => (string) $rate['id'],
                'name' => (string) $rate['name'],
                'percentage' => (string) ($rate['percentage'] ?? ''),
            ],
            $this->records(
                $this->clientFor($club)->get('tax_rates.json', ['filter' => 'tax_rate_type:sales_invoice', 'per_page' => 100]),
                fn (array $rate) => (bool) ($rate['active'] ?? true),
            ),
        );
    }

    public function listInvoiceWorkflows(Club $club): array
    {
        return array_map(
            fn (array $workflow) => ['id' => (string) $workflow['id'], 'name' => (string) $workflow['name']],
            $this->records(
                $this->clientFor($club)->get('workflows.json'),
                fn (array $workflow) => ($workflow['type'] ?? null) === 'InvoiceWorkflow' && ($workflow['active'] ?? true),
            ),
        );
    }

    public function listDocumentStyles(Club $club): array
    {
        return array_map(
            fn (array $style) => ['id' => (string) $style['id'], 'name' => (string) $style['name']],
            $this->records($this->clientFor($club)->get('document_styles.json')),
        );
    }

    public function upsertContact(Club $club, ?string $contactId, array $attributes): string
    {
        $client = $this->clientFor($club);

        $response = $contactId !== null
            ? $client->patch("contacts/{$contactId}.json", ['contact' => $attributes])
            : $client->post('contacts.json', ['contact' => $attributes]);

        return (string) $response->throw()->json('id');
    }

    public function findContactByCustomerId(Club $club, string $customerId): ?array
    {
        $response = $this->clientFor($club)->get('contacts/customer_id/'.rawurlencode($customerId).'.json');

        if ($response->notFound()) {
            return null;
        }

        $contact = $response->throw()->json();

        return [
            'id' => (string) $contact['id'],
            'email' => ($contact['email'] ?? null) ?: ($contact['send_invoices_to_email'] ?? null),
        ];
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

    public function getInvoice(Club $club, string $invoiceId): array
    {
        return $this->clientFor($club)->get("sales_invoices/{$invoiceId}.json")->throw()->json();
    }

    public function listInvoices(Club $club, ?CarbonInterface $updatedAfter = null, int $page = 1, int $perPage = 100): array
    {
        $filter = ['state:all'];

        if ($updatedAfter !== null) {
            $filter[] = 'updated_after:'.$updatedAfter->utc()->format('Y-m-d H:i:s');
        }

        return $this->clientFor($club)
            ->get('sales_invoices.json', [
                'filter' => implode(',', $filter),
                'page' => $page,
                'per_page' => $perPage,
            ])
            ->throw()
            ->json();
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

    /**
     * The records of a Moneybird list response, optionally filtered. Anything
     * that is not a JSON object is skipped. Failed responses throw.
     *
     * @param  (callable(array<string, mixed>): bool)|null  $keep
     * @return list<array<string, mixed>>
     */
    protected function records(Response $response, ?callable $keep = null): array
    {
        $records = $response->throw()->json();

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(
            $records,
            fn (mixed $record): bool => is_array($record) && ($keep === null || $keep($record)),
        ));
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
