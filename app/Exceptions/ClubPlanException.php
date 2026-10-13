<?php

namespace App\Exceptions;

use RuntimeException;

class ClubPlanException extends RuntimeException
{
    public static function planRequired(): self
    {
        return new self(__('This club needs an active SquadPass plan to add members.'));
    }

    public static function memberLimitReached(int $limit): self
    {
        return new self(__('This club has reached the limit of :limit active members of its plan. Upgrade the plan to add more members.', ['limit' => number_format($limit, 0, ',', '.')]));
    }

    public static function alreadySubscribed(): self
    {
        return new self(__('Your club already has a SquadPass plan.'));
    }

    public static function notSelfService(): self
    {
        return new self(__('This plan is arranged on request. Please contact us.'));
    }

    public static function tooManyMembersForPlan(int $limit): self
    {
        return new self(__('Your club has more active members than this plan allows (:limit).', ['limit' => number_format($limit, 0, ',', '.')]));
    }

    public static function paymentsUnavailable(): self
    {
        return new self(__('Plan payments are not available right now. Please contact support.'));
    }
}
