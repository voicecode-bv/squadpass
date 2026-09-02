<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Paused => __('Paused'),
            self::Ended => __('Ended'),
        };
    }
}
