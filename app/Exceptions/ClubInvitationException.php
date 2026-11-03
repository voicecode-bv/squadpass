<?php

namespace App\Exceptions;

use RuntimeException;

class ClubInvitationException extends RuntimeException
{
    public static function alreadyTrainer(): self
    {
        return new self(__('This person is already a trainer at this club.'));
    }

    public static function alreadyStaff(): self
    {
        return new self(__('This person already helps manage this club.'));
    }

    public static function alreadyMember(): self
    {
        return new self(__('This person is already a member of this club.'));
    }

    public static function demoSandbox(): self
    {
        return new self(__('Invitations are not sent from the demo. Create your own club to invite people.'));
    }

    public static function noLongerValid(): self
    {
        return new self(__('This invitation is no longer valid.'));
    }
}
