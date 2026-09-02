<?php

namespace App\Services\Mollie;

/**
 * The platform's own Mollie account, used to charge clubs for add-ons.
 */
class PlatformMollieAccount implements MollieAccount
{
    public function mollieApiKey(): ?string
    {
        return config('services.mollie.key') ?: null;
    }

    public function mollieCurrency(): string
    {
        return config('services.mollie.currency', 'EUR');
    }

    public function mollieAccountLabel(): string
    {
        return 'platform';
    }

    public function isConfigured(): bool
    {
        return $this->mollieApiKey() !== null;
    }
}
