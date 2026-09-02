<?php

namespace App\Services\Mollie;

use App\Models\Club;

interface MollieGateway
{
    /**
     * Create a Mollie customer for the club's Mollie account and return its id.
     */
    public function createCustomer(Club $club, string $name, string $email): string;

    /**
     * Create a payment on the club's Mollie account.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function createPayment(
        Club $club,
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
    public function getPayment(Club $club, string $paymentId): MolliePaymentResult;
}
