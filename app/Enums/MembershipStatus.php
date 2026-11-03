<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    /** Booked a trial lesson; not counted as a member until staff convert them. */
    case Trial = 'trial';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Paused => __('Paused'),
            self::Ended => __('Ended'),
            self::Trial => __('Trial member'),
        };
    }
}
