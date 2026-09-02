<?php

namespace App\Services\Mollie;

/**
 * Anything that owns a Mollie API key: a club (member payments) or the
 * platform itself (add-on billing).
 */
interface MollieAccount
{
    public function mollieApiKey(): ?string;

    public function mollieCurrency(): string;

    /**
     * Human readable identifier used in error messages and logs.
     */
    public function mollieAccountLabel(): string;
}
