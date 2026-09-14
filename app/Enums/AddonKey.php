<?php

namespace App\Enums;

/**
 * Sellable platform features. Each case has a matching row in the addons
 * table (name, description and price are managed by superadmins); the key
 * itself is what the application checks to unlock the feature.
 */
enum AddonKey: string
{
    case Mollie = 'mollie';
    case Moneybird = 'moneybird';
    case MobileApp = 'mobile_app';

    public function defaultName(): string
    {
        return match ($this) {
            self::Mollie => __('Online payments (Mollie)'),
            self::Moneybird => __('Invoicing (Moneybird)'),
            self::MobileApp => __('Branded mobile app'),
        };
    }

    public function defaultDescription(): string
    {
        return match ($this) {
            self::Mollie => __('Let members pay for strip cards and subscriptions online with your own Mollie account, including recurring direct debits.'),
            self::Moneybird => __('Sync members as Moneybird contacts and create a draft sales invoice for every paid payment.'),
            self::MobileApp => __('Your own club app in the App Store and Google Play, with your logo and colours, where members book lessons and check in.'),
        };
    }

    /**
     * Public path of the partner's (white) logo, for marketing surfaces.
     * Null for add-ons we build ourselves, which have no partner to credit.
     */
    public function logoPath(): ?string
    {
        return match ($this) {
            self::Mollie => 'images/logos/mollie.svg',
            self::Moneybird => 'images/logos/moneybird.svg',
            self::MobileApp => null,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Mollie => 'credit-card',
            self::Moneybird => 'document-text',
            self::MobileApp => 'device-phone-mobile',
        };
    }
}
