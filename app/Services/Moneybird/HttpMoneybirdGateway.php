<?php

namespace App\Services\Moneybird;

use App\Models\Club;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class HttpMoneybirdGateway implements MoneybirdGateway
{
    public function upsertContact(Club $club, ?string $contactId, array $attributes): string
    {
        $client = $this->clientFor($club);

        $response = $contactId !== null
            ? $client->patch("contacts/{$contactId}.json", ['contact' => $attributes])
            : $client->post('contacts.json', ['contact' => $attributes]);

        return (string) $response->throw()->json('id');
    }

    public function createInvoice(Club $club, string $contactId, array $lines): array
    {
        $response = $this->clientFor($club)->post('sales_invoices.json', [
            'sales_invoice' => [
                'contact_id' => $contactId,
                'details_attributes' => collect($lines)->map(fn (array $line) => [
                    'description' => $line['description'],
                    'price' => number_format($line['price_cents'] / 100, 2, '.', ''),
                    'amount' => '1',
                ])->all(),
            ],
        ]);

        return $response->throw()->json();
    }

    public function getInvoice(Club $club, string $invoiceId): array
    {
        return $this->clientFor($club)->get("sales_invoices/{$invoiceId}.json")->throw()->json();
    }

    public function listInvoices(Club $club, ?Carbon $updatedAfter = null, int $page = 1, int $perPage = 100): array
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

    public function createCreditInvoice(Club $club, string $invoiceId): array
    {
        return $this->clientFor($club)
            ->patch("sales_invoices/{$invoiceId}/duplicate_creditinvoice.json")
            ->throw()
            ->json();
    }

    protected function clientFor(Club $club): PendingRequest
    {
        if (! $club->hasMoneybirdConfigured()) {
            throw new InvalidArgumentException("Club [{$club->id}] has no Moneybird administration configured.");
        }

        return Http::baseUrl("https://moneybird.com/api/v2/{$club->moneybird_administration_id}/")
            ->withToken($club->moneybird_access_token)
            ->acceptJson();
    }
}
