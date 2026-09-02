<?php

namespace App\Enums;

enum LessonOccurrenceStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Scheduled'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
