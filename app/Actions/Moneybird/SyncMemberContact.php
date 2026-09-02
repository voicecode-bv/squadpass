<?php

namespace App\Actions\Moneybird;

use App\Models\Membership;
use App\Services\Moneybird\MoneybirdGateway;

class SyncMemberContact
{
    public function __construct(protected MoneybirdGateway $gateway) {}

    /**
     * Create or update the Moneybird contact for a membership and return the
     * contact id, or null when the club has no Moneybird administration.
     */
    public function handle(Membership $membership): ?string
    {
        $club = $membership->club;

        if (! $club->hasMoneybirdConfigured()) {
            return null;
        }

        $user = $membership->user;
        [$firstName, $lastName] = array_pad(explode(' ', trim($user->name), 2), 2, '');

        $contactId = $this->gateway->upsertContact($club, $membership->moneybird_contact_id, [
            'firstname' => $firstName,
            'lastname' => $lastName !== '' ? $lastName : $firstName,
            'send_invoices_to_email' => $user->email,
            'customer_id' => (string) $membership->membership_number,
        ]);

        $membership->update(['moneybird_contact_id' => $contactId]);

        $club->recordMoneybirdSuccess();

        return $contactId;
    }
}
