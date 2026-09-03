<?php

namespace App\Actions\Moneybird;

use App\Enums\MoneybirdInvoiceState;
use App\Models\Club;
use App\Models\Membership;
use App\Models\MoneybirdInvoice;
use App\Models\Payment;

class RecordInvoice
{
    /**
     * Store (or refresh) the local mirror of a Moneybird sales invoice so the
     * overview can filter and paginate without touching the API.
     *
     * @param  array<string, mixed>  $payload  A Moneybird sales invoice as returned by the API.
     * @param  array<string, int>|null  $membershipIdsByContact  Preloaded contact id => membership id map. When
     *                                                           given, no per-invoice membership lookup is done,
     *                                                           which keeps bulk syncs at a fixed query count.
     */
    public function handle(Club $club, array $payload, ?Payment $payment = null, ?array $membershipIdsByContact = null): MoneybirdInvoice
    {
        $contactId = isset($payload['contact_id']) ? (string) $payload['contact_id'] : null;
        $originalInvoiceId = isset($payload['original_sales_invoice_id']) ? (string) $payload['original_sales_invoice_id'] : null;
        $totalCents = $this->toCents($payload['total_price_incl_tax'] ?? 0);

        return MoneybirdInvoice::query()->updateOrCreate(
            [
                'club_id' => $club->id,
                'moneybird_id' => (string) $payload['id'],
            ],
            [
                'membership_id' => $this->membershipId($club, $contactId, $membershipIdsByContact),
                'payment_id' => $payment?->id,
                'credited_invoice_id' => $this->creditedInvoiceId($club, $originalInvoiceId),
                'moneybird_contact_id' => $contactId,
                'invoice_number' => filled($payload['invoice_id'] ?? null) ? (string) $payload['invoice_id'] : null,
                'reference' => filled($payload['reference'] ?? null) ? (string) $payload['reference'] : null,
                'state' => MoneybirdInvoiceState::fromApi($payload['state'] ?? null),
                'invoice_date' => $payload['invoice_date'] ?? null,
                'total_price_incl_tax_cents' => $totalCents,
                'currency' => $payload['currency'] ?? $club->currency,
                'is_credit_invoice' => $originalInvoiceId !== null || $totalCents < 0,
                'lines' => $this->lines($payload['details'] ?? []),
                'moneybird_updated_at' => $payload['updated_at'] ?? null,
                'synced_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, int>|null  $membershipIdsByContact
     */
    protected function membershipId(Club $club, ?string $contactId, ?array $membershipIdsByContact): ?int
    {
        if ($contactId === null) {
            return null;
        }

        if ($membershipIdsByContact !== null) {
            return $membershipIdsByContact[$contactId] ?? null;
        }

        return Membership::query()
            ->where('club_id', $club->id)
            ->where('moneybird_contact_id', $contactId)
            ->value('id');
    }

    protected function creditedInvoiceId(Club $club, ?string $originalInvoiceId): ?int
    {
        if ($originalInvoiceId === null) {
            return null;
        }

        return MoneybirdInvoice::query()
            ->where('club_id', $club->id)
            ->where('moneybird_id', $originalInvoiceId)
            ->value('id');
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     * @return list<array{description: string, amount: string|null, price_cents: int, total_price_excl_tax_cents: int}>
     */
    protected function lines(array $details): array
    {
        return collect($details)->map(fn (array $detail) => [
            'description' => (string) ($detail['description'] ?? ''),
            'amount' => isset($detail['amount']) ? (string) $detail['amount'] : null,
            'price_cents' => $this->toCents($detail['price'] ?? 0),
            'total_price_excl_tax_cents' => $this->toCents($detail['total_price_excl_tax_with_discount'] ?? 0),
        ])->values()->all();
    }

    protected function toCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
