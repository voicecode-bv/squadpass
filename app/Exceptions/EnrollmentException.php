<?php

namespace App\Exceptions;

use App\Models\LessonOccurrence;
use RuntimeException;

class EnrollmentException extends RuntimeException
{
    public static function membershipRequired(): self
    {
        return new self(__('An active membership at this club is required.'));
    }

    public static function alreadyEnrolled(): self
    {
        return new self(__('You are already enrolled for this lesson.'));
    }

    public static function notBookable(): self
    {
        return new self(__('This lesson cannot be booked.'));
    }

    public static function noCredits(): self
    {
        return new self(__('Your subscription has no credits left for this period.'));
    }

    public static function full(LessonOccurrence $occurrence): self
    {
        return new self(__('The lesson on :date is full.', [
            'date' => $occurrence->starts_at->timezone($occurrence->club->timezone)->isoFormat('D MMMM YYYY HH:mm'),
        ]));
    }
}
