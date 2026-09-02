<?php

namespace App\Services\Moneybird;

use App\Models\Club;
use Illuminate\Http\Client\PendingRequest;
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

    public function createInvoice(Club $club, string $contactId, array $lines): string
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

        return (string) $response->throw()->json('id');
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
