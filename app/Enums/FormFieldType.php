<?php

namespace App\Enums;

enum FormFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Email = 'email';
    case Phone = 'phone';
    case Select = 'select';
    case Radio = 'radio';
    case Checkboxes = 'checkboxes';
    case Checkbox = 'checkbox';
    case Consent = 'consent';
    case Signature = 'signature';
    case Heading = 'heading';

    public function label(): string
    {
        return match ($this) {
            self::Text => __('Short text'),
            self::Textarea => __('Long text'),
            self::Number => __('Number'),
            self::Date => __('Date'),
            self::Email => __('Email address'),
            self::Phone => __('Phone number'),
            self::Select => __('Dropdown'),
            self::Radio => __('Single choice'),
            self::Checkboxes => __('Multiple choice'),
            self::Checkbox => __('Yes/no'),
            self::Consent => __('Consent statement'),
            self::Signature => __('Signature'),
            self::Heading => __('Section heading'),
        };
    }

    /**
     * Types that let the owner configure a list of answer options.
     */
    public function hasOptions(): bool
    {
        return match ($this) {
            self::Select, self::Radio, self::Checkboxes => true,
            default => false,
        };
    }

    /**
     * Heading fields are layout only: they never hold an answer.
     */
    public function storesAnswer(): bool
    {
        return $this !== self::Heading;
    }

    /**
     * Consent statements carry their text in the help attribute, so the
     * builder labels that input differently.
     */
    public function isBoolean(): bool
    {
        return match ($this) {
            self::Checkbox, self::Consent => true,
            default => false,
        };
    }
}
