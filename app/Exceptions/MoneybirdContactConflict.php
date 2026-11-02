<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Moneybird already has a contact with the member's customer number, and it
 * belongs to someone else. We never overwrite it; the club has to resolve it.
 */
class MoneybirdContactConflict extends RuntimeException
{
    public static function customerNumberTaken(string $customerNumber, ?string $existingEmail): self
    {
        return new self(__('Moneybird already has a contact with customer number :number (:email). Change the customer number of that contact in Moneybird, or link it manually.', [
            'number' => $customerNumber,
            'email' => $existingEmail ?: __('no email address'),
        ]));
    }
}
