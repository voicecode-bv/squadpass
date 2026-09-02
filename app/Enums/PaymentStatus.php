<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Canceled = 'canceled';
    case Refunded = 'refunded';

    public function isFinal(): bool
    {
        return $this !== self::Open;
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Paid => __('Paid'),
            self::Failed => __('Failed'),
            self::Expired => __('Expired'),
            self::Canceled => __('Canceled'),
            self::Refunded => __('Refunded'),
        };
    }
}
