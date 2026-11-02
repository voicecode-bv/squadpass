<?php

namespace App\Actions\Moneybird;

use App\Exceptions\MoneybirdContactConflict;
use App\Models\Membership;
use App\Services\Moneybird\MoneybirdGateway;
use App\Services\PersonName;
use Illuminate\Support\Str;

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
        // Moneybird has no prefix field, so "van der" goes in front of the last name.
        $lastName = PersonName::fullLastName($user->name_prefix, $user->last_name);

        $customerId = (string) $membership->membership_number;

        $contactId = $this->gateway->upsertContact($club, $membership->moneybird_contact_id ?? $this->existingContactId($membership, $customerId), [
            'firstname' => $user->first_name,
            'lastname' => $lastName !== '' ? $lastName : $user->first_name,
            'send_invoices_to_email' => $user->email,
            'customer_id' => $customerId,
        ]);

        $membership->update(['moneybird_contact_id' => $contactId]);

        $club->recordMoneybirdSuccess();

        return $contactId;
    }

    /**
     * A member without a linked contact may already exist in Moneybird under
     * their customer number, e.g. from an earlier sync or the club's previous
     * system. That contact is reused when it is the same person (same email,
     * or none); a contact of someone else is never overwritten.
     *
     * @throws MoneybirdContactConflict
     */
    protected function existingContactId(Membership $membership, string $customerId): ?string
    {
        $existing = $this->gateway->findContactByCustomerId($membership->club, $customerId);

        if ($existing === null) {
            return null;
        }

        if (filled($existing['email']) && Str::lower($existing['email']) !== Str::lower($membership->user->email)) {
            $exception = MoneybirdContactConflict::customerNumberTaken($customerId, $existing['email']);
            $membership->club->recordMoneybirdError($exception->getMessage());

            throw $exception;
        }

        return $existing['id'];
    }
}
