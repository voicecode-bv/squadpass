<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending payment'),
            self::Active => __('Active'),
            self::PastDue => __('Payment overdue'),
            self::Cancelled => __('Cancelled'),
            self::Ended => __('Ended'),
        };
    }
}
