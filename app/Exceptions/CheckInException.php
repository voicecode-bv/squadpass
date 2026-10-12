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

    public static function invalidCode(): self
    {
        return new self(__('This QR code is no longer valid. Show a fresh code and scan again.'));
    }

    public static function recentlyCheckedIn(): self
    {
        return new self(__('A check-in for this member was already registered a moment ago.'));
    }
}
