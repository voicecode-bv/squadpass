<?php

namespace App\Enums;

enum MoneybirdInvoiceState: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Scheduled = 'scheduled';
    case PendingPayment = 'pending_payment';
    case Reminded = 'reminded';
    case Late = 'late';
    case Paid = 'paid';
    case Uncollectible = 'uncollectible';
    case Unknown = 'unknown';

    /**
     * Moneybird may introduce states we do not know yet; those are kept as
     * Unknown instead of breaking the sync.
     */
    public static function fromApi(?string $state): self
    {
        return self::tryFrom((string) $state) ?? self::Unknown;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Open => __('Open'),
            self::Scheduled => __('Scheduled'),
            self::PendingPayment => __('Pending payment'),
            self::Reminded => __('Reminded'),
            self::Late => __('Late'),
            self::Paid => __('Paid'),
            self::Uncollectible => __('Uncollectible'),
            self::Unknown => __('Unknown'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'green',
            self::Open, self::Scheduled, self::PendingPayment => 'blue',
            self::Reminded, self::Late => 'amber',
            self::Uncollectible => 'red',
            self::Draft, self::Unknown => 'zinc',
        };
    }

    /**
     * States a club can filter the invoice overview on.
     *
     * @return list<self>
     */
    public static function filterable(): array
    {
        return [self::Draft, self::Open, self::PendingPayment, self::Reminded, self::Late, self::Paid, self::Uncollectible];
    }
}
