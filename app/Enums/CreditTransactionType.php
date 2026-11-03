<?php

namespace App\Enums;

/**
 * What happened to a member's lesson credits; each case is one line in the credit timeline.
 */
enum CreditTransactionType: string
{
    /** Credits added for a (new) billing period. */
    case Granted = 'granted';

    /** Credits spent on a reservation or recurring enrollment. */
    case Consumed = 'consumed';

    /** Credits returned after cancelling in time or marking absent. */
    case Refunded = 'refunded';

    /** Credits kept by the club because the member cancelled after the cancellation deadline. */
    case Forfeited = 'forfeited';

    /** Unused credits removed from the balance once their expiry date passed. */
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Granted => __('Credits added'),
            self::Consumed => __('Credits used'),
            self::Refunded => __('Credits refunded'),
            self::Forfeited => __('Late cancellation'),
            self::Expired => __('Credits expired'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Granted, self::Refunded => 'green',
            self::Consumed => 'blue',
            self::Forfeited => 'amber',
            self::Expired => 'zinc',
        };
    }
}
