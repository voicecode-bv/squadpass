<?php

namespace App\Concerns;

trait DescribesCreditOutcome
{
    /**
     * A cancellation message that tells the member what happened to their credits.
     *
     * @param  array{refunded: int, forfeited: int}  $outcome
     */
    protected function creditOutcomeMessage(string $message, array $outcome): string
    {
        if ($outcome['forfeited'] > 0) {
            return $message.' '.trans_choice(
                'You cancelled after the cancellation deadline, so :count credit was not refunded.|You cancelled after the cancellation deadline, so :count credits were not refunded.',
                $outcome['forfeited'],
            );
        }

        if ($outcome['refunded'] > 0) {
            return $message.' '.trans_choice(':count credit was refunded.|:count credits were refunded.', $outcome['refunded']);
        }

        return $message;
    }
}
