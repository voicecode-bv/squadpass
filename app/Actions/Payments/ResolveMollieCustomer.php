<?php

namespace App\Actions\Payments;

use App\Models\Club;
use App\Models\MollieCustomer;
use App\Models\User;
use App\Services\Mollie\MollieGateway;

class ResolveMollieCustomer
{
    public function __construct(protected MollieGateway $gateway) {}

    /**
     * Get the Mollie customer id for this (club, user) pair, creating one on first use.
     */
    public function handle(Club $club, User $user): string
    {
        $existing = MollieCustomer::query()
            ->where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return $existing->mollie_customer_id;
        }

        $customerId = $this->gateway->createCustomer($club, $user->name, $user->email);

        MollieCustomer::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'mollie_customer_id' => $customerId,
        ]);

        return $customerId;
    }
}
