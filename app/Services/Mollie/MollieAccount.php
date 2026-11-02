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

    /**
     * Remember a failed Mollie request so it can be shown to the account owner.
     */
    public function recordMollieError(string $message): void;

    /**
     * Forget a previously recorded failure after a successful request.
     */
    public function recordMollieSuccess(): void;
}
