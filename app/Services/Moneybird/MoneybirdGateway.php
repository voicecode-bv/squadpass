<?php

namespace App\Services\Moneybird;

use App\Models\Club;

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
     * Create a draft sales invoice for a contact and return its Moneybird id.
     *
     * @param  list<array{description: string, price_cents: int}>  $lines
     */
    public function createInvoice(Club $club, string $contactId, array $lines): string;
}
