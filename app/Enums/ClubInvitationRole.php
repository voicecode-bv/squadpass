<?php

namespace App\Enums;

enum ClubInvitationRole: string
{
    case Trainer = 'trainer';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Trainer => __('Trainer'),
            self::Member => __('Member'),
        };
    }
}
