<?php

namespace App\Actions\Mollie;

use App\Models\Club;
use App\Services\Mollie\MollieGateway;

class ConnectMollieAccount
{
    public function __construct(protected MollieGateway $gateway) {}

    /**
     * Verify the API key with Mollie and store it with the profile it belongs
     * to. A key Mollie rejects is not stored, so a typo never reaches members
     * at checkout.
     *
     * @return bool Whether Mollie accepted the key.
     */
    public function handle(Club $club, string $apiKey): bool
    {
        $profile = $this->gateway->fetchProfile($apiKey);

        if ($profile === null) {
            return false;
        }

        $club->forceFill([
            'mollie_api_key' => $apiKey,
            'mollie_profile_id' => $profile->id,
            'mollie_profile_name' => $profile->name,
            'mollie_last_error' => null,
        ])->save();

        return true;
    }

    /**
     * Remove the key and everything derived from it.
     */
    public function disconnect(Club $club): void
    {
        $club->forceFill([
            'mollie_api_key' => null,
            'mollie_profile_id' => null,
            'mollie_profile_name' => null,
            'mollie_last_error' => null,
        ])->save();
    }
}
