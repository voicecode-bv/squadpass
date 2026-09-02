<?php

namespace App\Services\Mollie;

use App\Enums\PaymentStatus;
use App\Models\Club;
use InvalidArgumentException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment as MolliePayment;

class HttpMollieGateway implements MollieGateway
{
    public function createCustomer(Club $club, string $name, string $email): string
    {
        $customer = $this->clientFor($club)->customers->create([
            'name' => $name,
            'email' => $email,
        ]);

        return $customer->id;
    }

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
    ): MolliePaymentResult {
        $attributes = [
            'amount' => [
                'currency' => $club->currency,
                'value' => number_format($amountCents / 100, 2, '.', ''),
            ],
            'description' => $description,
            'redirectUrl' => $redirectUrl,
            'webhookUrl' => $webhookUrl,
            'metadata' => $metadata,
        ];

        if ($customerId !== null) {
            $attributes['customerId'] = $customerId;
        }

        if ($sequenceType !== null) {
            $attributes['sequenceType'] = $sequenceType;
        }

        if ($mandateId !== null) {
            $attributes['mandateId'] = $mandateId;
        }

        return $this->toResult($this->clientFor($club)->payments->create($attributes));
    }

    public function getPayment(Club $club, string $paymentId): MolliePaymentResult
    {
        return $this->toResult($this->clientFor($club)->payments->get($paymentId));
    }

    protected function clientFor(Club $club): MollieApiClient
    {
        if (! $club->mollie_api_key) {
            throw new InvalidArgumentException("Club [{$club->slug}] has no Mollie API key configured.");
        }

        $client = new MollieApiClient;
        $client->setApiKey($club->mollie_api_key);

        return $client;
    }

    protected function toResult(MolliePayment $payment): MolliePaymentResult
    {
        // Mollie's transient statuses (pending, authorized) stay Open on our side.
        $status = match ($payment->status) {
            'pending', 'authorized' => PaymentStatus::Open,
            default => PaymentStatus::from($payment->status),
        };

        return new MolliePaymentResult(
            id: $payment->id,
            status: $status,
            checkoutUrl: $payment->getCheckoutUrl(),
            mandateId: $payment->mandateId,
            metadata: (array) $payment->metadata,
        );
    }
}
