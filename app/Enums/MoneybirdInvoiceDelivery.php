<?php

namespace App\Enums;

/**
 * What happens with a Moneybird sales invoice after SquadPass creates it.
 */
enum MoneybirdInvoiceDelivery: string
{
    case Draft = 'draft';
    case Manual = 'manual';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Keep as draft'),
            self::Manual => __('Finalize without sending'),
            self::Email => __('Finalize and email to the member'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Draft => __('You review and send every invoice yourself in Moneybird. Payments are not registered.'),
            self::Manual => __('The invoice gets a number and the Mollie payment is registered on it, but the member receives nothing from Moneybird.'),
            self::Email => __('Moneybird emails the invoice to the member and the Mollie payment is registered on it.'),
        };
    }

    public function finalizes(): bool
    {
        return $this !== self::Draft;
    }

    /**
     * Value for Moneybird's sales_invoice_sending.delivery_method.
     */
    public function moneybirdDeliveryMethod(): string
    {
        return match ($this) {
            self::Email => 'Email',
            default => 'Manual',
        };
    }
}
