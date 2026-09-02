<?php

namespace App\Services\Mollie;

use App\Enums\PaymentStatus;

class MolliePaymentResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly PaymentStatus $status,
        public readonly ?string $checkoutUrl = null,
        public readonly ?string $mandateId = null,
        public readonly array $metadata = [],
    ) {}
}
