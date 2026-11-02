<?php

namespace App\Services\Mollie;

use App\Enums\PaymentStatus;
use BackedEnum;
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

        // Resolved from the container so tests can swap in Mollie's MockMollieClient.
        $client = app(MollieApiClient::class);
        $client->setApiKey($account->mollieApiKey());

        return $client;
    }

    protected function toResult(MolliePayment $payment): MolliePaymentResult
    {
        // Since mollie-api-php v4 a known status is a Mollie enum; unknown ones stay a string.
        $mollieStatus = $payment->status instanceof BackedEnum ? $payment->status->value : $payment->status;

        // Mollie's transient statuses (pending, authorized) stay Open on our side.
        $status = match ($mollieStatus) {
            'pending', 'authorized' => PaymentStatus::Open,
            default => PaymentStatus::from($mollieStatus),
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
