<?php

namespace App\Services\Mollie;

interface MollieGateway
{
    /**
     * Create a Mollie customer on the account and return its id.
     */
    public function createCustomer(MollieAccount $account, string $name, string $email): string;

    /**
     * Create a payment on the account.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function createPayment(
        MollieAccount $account,
        int $amountCents,
        string $description,
        string $redirectUrl,
        string $webhookUrl,
        ?string $customerId = null,
        ?string $sequenceType = null,
        ?string $mandateId = null,
        array $metadata = [],
    ): MolliePaymentResult;

    /**
     * Fetch the current state of a payment from Mollie.
     */
    public function getPayment(MollieAccount $account, string $paymentId): MolliePaymentResult;

    /**
     * The profile an API key belongs to, or null when Mollie rejects the key.
     * Used to verify a key before it is stored.
     */
    public function fetchProfile(string $apiKey): ?MollieProfile;
}
