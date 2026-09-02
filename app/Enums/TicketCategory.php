<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Billing = 'billing';
    case Lessons = 'lessons';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Billing => __('Billing'),
            self::Lessons => __('Lessons'),
            self::Other => __('Other'),
        };
    }
}
