<?php

namespace App\Enums;

enum StaffRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Admin'),
        };
    }
}
