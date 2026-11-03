<?php

namespace App\Enums;

enum StaffRole: string
{
    case Owner = 'owner';
    /** A staff member with the permissions the owner ticked (App\Enums\StaffPermission). */
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Staff member'),
        };
    }
}
