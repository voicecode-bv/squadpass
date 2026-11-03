<?php

namespace App\Exceptions;

use RuntimeException;

class TrialLessonException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self(__('This lesson cannot be booked as a trial lesson.'));
    }

    public static function full(): self
    {
        return new self(__('This lesson is full. Choose another moment.'));
    }

    public static function alreadyMember(): self
    {
        return new self(__('You are already a member of this club. Log in to book lessons.'));
    }

    public static function alreadyKnown(): self
    {
        return new self(__('You have already had a trial lesson at this club. Contact the club to become a member.'));
    }
}
