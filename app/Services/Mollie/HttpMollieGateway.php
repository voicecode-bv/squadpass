<?php

namespace App\Services\Mollie;

use App\Enums\PaymentStatus;
use InvalidArgumentException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment as MolliePayment;

class HttpMollieGateway implements MollieGateway
{
    public function createCustomer(MollieAccount $account, string $name, string $email): string
    {
        $customer = $this->clientFor($account)->customers->create([
            'name' => $name,
            'email' => $email,
        ]);

        return $customer->id;
    }

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
    ): MolliePaymentResult {
        $attributes = [
            'amount' => [
                'currency' => $account->mollieCurrency(),
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

        return $this->toResult($this->clientFor($account)->payments->create($attributes));
    }

    public function getPayment(MollieAccount $account, string $paymentId): MolliePaymentResult
    {
        return $this->toResult($this->clientFor($account)->payments->get($paymentId));
    }

    protected function clientFor(MollieAccount $account): MollieApiClient
    {
        if (! $account->mollieApiKey()) {
            throw new InvalidArgumentException("Mollie account [{$account->mollieAccountLabel()}] has no API key configured.");
        }

        $client = new MollieApiClient;
        $client->setApiKey($account->mollieApiKey());

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
