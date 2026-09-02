<?php

namespace App\Exceptions;

use RuntimeException;

class CheckInException extends RuntimeException
{
    public static function membershipRequired(): self
    {
        return new self(__('An active membership at this club is required.'));
    }

    public static function alreadyCheckedIn(): self
    {
        return new self(__('This member is already checked in for this lesson.'));
    }

    public static function noCoverage(): self
    {
        return new self(__('No coverage: no unlimited subscription and no usable strip card.'));
    }
}
