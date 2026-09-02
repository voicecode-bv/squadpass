<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function addTo(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::Monthly => $date->addMonthNoOverflow(),
            self::Yearly => $date->addYearNoOverflow(),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Monthly => __('Per month'),
            self::Yearly => __('Per year'),
        };
    }
}
