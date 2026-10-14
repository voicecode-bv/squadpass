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
     * Inviting headline for the in-app call to action when the add-on is missing.
     */
    public function pitch(): string
    {
        return match ($this) {
            self::Mollie => __('Get paid online, without chasing anyone'),
            self::Moneybird => __('Your invoicing on autopilot with Moneybird'),
            self::MobileApp => __('Your club in every member\'s pocket'),
        };
    }

    /**
     * The key selling points shown in the in-app call to action.
     *
     * @return array<int, string>
     */
    public function benefits(): array
    {
        return match ($this) {
            self::Mollie => [
                __('Members pay for strip cards and subscriptions with iDEAL, card or direct debit'),
                __('Recurring direct debits are collected automatically every month'),
                __('Payments land straight in your own Mollie account'),
            ],
            self::Moneybird => [
                __('Every paid payment becomes a sales invoice automatically'),
                __('Members are synced as Moneybird contacts'),
                __('View and credit invoices right here in SquadPass'),
            ],
            self::MobileApp => [
                __('Your own app in the App Store and Google Play'),
                __('Members book lessons and check in with their phone'),
                __('Your logo and colours on every screen'),
            ],
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
