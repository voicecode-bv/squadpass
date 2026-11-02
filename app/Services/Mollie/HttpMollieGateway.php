<?php

namespace App\Services\Mollie;

use App\Enums\PaymentStatus;
use BackedEnum;
use Closure;
use InvalidArgumentException;
use Mollie\Api\Exceptions\ForbiddenException;
use Mollie\Api\Exceptions\InvalidAuthenticationException;
use Mollie\Api\Exceptions\UnauthorizedException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment as MolliePayment;
use Throwable;

class HttpMollieGateway implements MollieGateway
{
    public function createCustomer(MollieAccount $account, string $name, string $email): string
    {
        return $this->send($account, fn (MollieApiClient $client): string => $client->customers->create([
            'name' => $name,
            'email' => $email,
        ])->id);
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

        return $this->send($account, fn (MollieApiClient $client): MolliePaymentResult => $this->toResult($client->payments->create($attributes)));
    }

    public function getPayment(MollieAccount $account, string $paymentId): MolliePaymentResult
    {
        return $this->send($account, fn (MollieApiClient $client): MolliePaymentResult => $this->toResult($client->payments->get($paymentId)));
    }

    public function fetchProfile(string $apiKey): ?MollieProfile
    {
        try {
            $client = app(MollieApiClient::class);
            $client->setApiKey($apiKey);

            $profile = $client->profiles->getCurrent();
        } catch (InvalidAuthenticationException|UnauthorizedException|ForbiddenException) {
            // Malformed, revoked or unknown key: the caller turns this into a validation error.
            return null;
        }

        return new MollieProfile(id: $profile->id, name: $profile->name);
    }

    /**
     * Run a request for an account and record its outcome on the account, so a
     * club sees the last failure on its Mollie page. Failures are rethrown.
     *
     * @template TResult
     *
     * @param  Closure(MollieApiClient): TResult  $request
     * @return TResult
     */
    protected function send(MollieAccount $account, Closure $request): mixed
    {
        try {
            $result = $request($this->clientFor($account));
        } catch (Throwable $exception) {
            $account->recordMollieError($exception->getMessage());

            throw $exception;
        }

        $account->recordMollieSuccess();

        return $result;
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
