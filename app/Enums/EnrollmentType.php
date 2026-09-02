<?php

namespace App\Enums;

enum EnrollmentType: string
{
    case Fixed = 'fixed';
    case Single = 'single';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Recurring'),
            self::Single => __('One-time'),
        };
    }
}
