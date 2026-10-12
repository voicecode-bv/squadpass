<?php

namespace App\Enums;

enum FormAudience: string
{
    /** Every active member of the club sees the form in their portal. */
    case AllMembers = 'all_members';

    /** Only members the club assigned the form to see it. */
    case Assigned = 'assigned';

    public function label(): string
    {
        return match ($this) {
            self::AllMembers => __('All members'),
            self::Assigned => __('Assigned members only'),
        };
    }
}
