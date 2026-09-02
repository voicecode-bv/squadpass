<?php

namespace Tests\Fakes;

use App\Models\Club;
use App\Services\Moneybird\MoneybirdGateway;

class FakeMoneybirdGateway implements MoneybirdGateway
{
    /** @var array<int, array{club: int, contact_id: string|null, attributes: array<string, mixed>, id: string}> */
    public array $upsertedContacts = [];

    /** @var array<int, array{club: int, contact_id: string, lines: array<int, array{description: string, price_cents: int}>, id: string}> */
    public array $createdInvoices = [];

    protected int $sequence = 0;

    public function upsertContact(Club $club, ?string $contactId, array $attributes): string
    {
        $id = $contactId ?? 'mb_contact_'.(++$this->sequence);

        $this->upsertedContacts[] = ['club' => $club->id, 'contact_id' => $contactId, 'attributes' => $attributes, 'id' => $id];

        return $id;
    }

    public function createInvoice(Club $club, string $contactId, array $lines): string
    {
        $id = 'mb_invoice_'.(++$this->sequence);

        $this->createdInvoices[] = ['club' => $club->id, 'contact_id' => $contactId, 'lines' => $lines, 'id' => $id];

        return $id;
    }
}
