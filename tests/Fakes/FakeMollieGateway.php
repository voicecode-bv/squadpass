<?php

namespace Tests\Fakes;

use App\Enums\PaymentStatus;
use App\Models\Club;
use App\Services\Mollie\MollieGateway;
use App\Services\Mollie\MolliePaymentResult;
use RuntimeException;

class FakeMollieGateway implements MollieGateway
{
    /** @var array<int, array{club: int, name: string, email: string, id: string}> */
    public array $createdCustomers = [];

    /** @var array<int, array<string, mixed>> */
    public array $createdPayments = [];

    /** @var array<string, MolliePaymentResult> */
    protected array $payments = [];

    protected int $sequence = 0;

    public function createCustomer(Club $club, string $name, string $email): string
    {
        $id = 'cst_fake'.(++$this->sequence);

        $this->createdCustomers[] = ['club' => $club->id, 'name' => $name, 'email' => $email, 'id' => $id];

        return $id;
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
        $id = 'tr_fake'.(++$this->sequence);

        $this->createdPayments[] = [
            'id' => $id,
            'club' => $club->id,
            'amount_cents' => $amountCents,
            'description' => $description,
            'redirect_url' => $redirectUrl,
            'webhook_url' => $webhookUrl,
            'customer_id' => $customerId,
            'sequence_type' => $sequenceType,
            'mandate_id' => $mandateId,
            'metadata' => $metadata,
        ];

        return $this->payments[$id] = new MolliePaymentResult(
            id: $id,
            status: PaymentStatus::Open,
            checkoutUrl: "https://mollie.test/checkout/{$id}",
            metadata: $metadata,
        );
    }

    public function getPayment(Club $club, string $paymentId): MolliePaymentResult
    {
        return $this->payments[$paymentId]
            ?? throw new RuntimeException("Unknown fake Mollie payment [{$paymentId}].");
    }

    public function setPaymentStatus(string $paymentId, PaymentStatus $status, ?string $mandateId = null): void
    {
        $current = $this->payments[$paymentId] ?? throw new RuntimeException("Unknown fake Mollie payment [{$paymentId}].");

        $this->payments[$paymentId] = new MolliePaymentResult(
            id: $current->id,
            status: $status,
            checkoutUrl: $current->checkoutUrl,
            mandateId: $mandateId ?? $current->mandateId,
            metadata: $current->metadata,
        );
    }
}
