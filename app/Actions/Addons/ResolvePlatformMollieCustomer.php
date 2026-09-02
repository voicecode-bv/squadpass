<?php

namespace App\Actions\Addons;

use App\Models\Club;
use App\Services\Mollie\MollieGateway;
use App\Services\Mollie\PlatformMollieAccount;

class ResolvePlatformMollieCustomer
{
    public function __construct(
        protected MollieGateway $gateway,
        protected PlatformMollieAccount $platform,
    ) {}

    /**
     * Get the club's customer id on the platform Mollie account, creating one on first use.
     */
    public function handle(Club $club, string $contactEmail): string
    {
        if ($club->platform_mollie_customer_id) {
            return $club->platform_mollie_customer_id;
        }

        $customerId = $this->gateway->createCustomer($this->platform, $club->name, $contactEmail);

        $club->update(['platform_mollie_customer_id' => $customerId]);

        return $customerId;
    }
}
