<?php

namespace App\Exceptions;

use RuntimeException;

class FormException extends RuntimeException
{
    public static function notPublished(): self
    {
        return new self(__('This form is not available.'));
    }

    public static function emptySchema(): self
    {
        return new self(__('Add at least one field before publishing.'));
    }

    public static function notAvailableToUser(): self
    {
        return new self(__('This form is not available to you.'));
    }

    public static function alreadySubmitted(): self
    {
        return new self(__('You already sent this form in.'));
    }
}
