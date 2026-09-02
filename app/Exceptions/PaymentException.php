<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentException extends RuntimeException
{
    public static function onlinePaymentsUnavailable(): self
    {
        return new self(__('Online payments are not available for this club yet.'));
    }

    public static function platformPaymentsUnavailable(): self
    {
        return new self(__('Add-on payments are not available right now. Please contact support.'));
    }

    public static function addonUnavailable(): self
    {
        return new self(__('This add-on is not available.'));
    }

    public static function addonAlreadyActive(): self
    {
        return new self(__('Your club already has this add-on.'));
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
