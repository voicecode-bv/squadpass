<?php

namespace App\Exceptions;

use RuntimeException;

class MoneybirdException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self(__('This club has no Moneybird administration connected.'));
    }

    public static function alreadyCredited(): self
    {
        return new self(__('This invoice has already been credited.'));
    }

    public static function cannotCreditCreditInvoice(): self
    {
        return new self(__('A credit invoice cannot be credited again.'));
    }
}
