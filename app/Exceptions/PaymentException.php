<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentException extends RuntimeException
{
    public static function onlinePaymentsUnavailable(): self
    {
        return new self(__('Online payments are not available for this club yet.'));
    }

    public static function membershipRequired(): self
    {
        return new self(__('An active membership at this club is required.'));
    }

    public static function subscriptionAlreadyExists(): self
    {
        return new self(__('You already have a subscription at this club.'));
    }
}
