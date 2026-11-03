<?php

namespace App\Services\Credits;

use App\Enums\CreditTransactionType;
use App\Models\CreditGrant;
use App\Models\CreditTransaction;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only place that changes lesson credits. Every change updates the cached
 * balance on the subscription atomically, books it against the credit grants
 * (so credits can expire) and writes a line to the member's credit timeline.
 */
class CreditLedger
{
    /**
     * Add a batch of credits for a new billing period, expiring after the club's expiry period.
     */
    public function grant(Subscription $subscription, int $amount, ?User $actor = null): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($subscription, $amount, $actor): void {
            $expiryDays = $subscription->club->credit_expiry_days;

            $grant = CreditGrant::create([
                'club_id' => $subscription->club_id,
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'amount' => $amount,
                'remaining' => $amount,
                'expires_at' => $expiryDays ? now()->addDays($expiryDays) : null,
            ]);

            $subscription->addCredits($amount);

            $this->record($subscription, CreditTransactionType::Granted, $amount, grant: $grant, actor: $actor);
        });
    }

    /**
     * Add the monthly credits of a subscription once its credit date has come,
     * and move that date a month on. Billing has its own schedule: a yearly plan
     * still gets its credits every month. Safe to run twice for the same month.
     */
    public function grantMonthly(Subscription $subscription): bool
    {
        $due = $subscription->next_credits_at;

        if ($due === null || $due->isAfter(today())) {
            return false;
        }

        $next = $subscription->nextCreditDateAfter(today());

        return DB::transaction(function () use ($subscription, $due, $next): bool {
            $claimed = Subscription::query()
                ->whereKey($subscription->id)
                ->whereDate('next_credits_at', $due)
                ->update(['next_credits_at' => $next->toDateString()]) === 1;

            if (! $claimed) {
                return false;
            }

            $subscription->next_credits_at = $next;
            $plan = $subscription->plan;

            if ($plan->isUnlimited()) {
                Subscription::query()->whereKey($subscription->id)->update(['credits_remaining' => null]);
                $subscription->credits_remaining = null;

                return true;
            }

            $this->grant($subscription, $plan->credits);

            return true;
        });
    }

    /**
     * Spend credits on an enrollment, taking them from the grant that expires first.
     * Unlimited subscriptions always succeed; returns false when too few credits remain.
     */
    public function consume(
        Subscription $subscription,
        int $count,
        LessonEnrollment $enrollment,
        ?LessonOccurrence $occurrence = null,
        ?User $actor = null,
    ): bool {
        if ($subscription->isUnlimited() || $count <= 0) {
            return true;
        }

        if (! $subscription->consumeCredits($count)) {
            return false;
        }

        $grants = $subscription->creditGrants()
            ->where('remaining', '>', 0)
            ->unexpired()
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $balance = $subscription->credits_remaining + $count;
        $left = $count;

        foreach ($grants as $grant) {
            $taken = min($left, $grant->remaining);
            $grant->decrement('remaining', $taken);

            $balance -= $taken;
            $left -= $taken;

            $this->record($subscription, CreditTransactionType::Consumed, -$taken, $balance, $grant, $enrollment, $occurrence, $actor);

            if ($left === 0) {
                return true;
            }
        }

        // Balance without a grant (set before credits were tracked per grant).
        $this->record($subscription, CreditTransactionType::Consumed, -$left, $balance - $left, null, $enrollment, $occurrence, $actor);

        return true;
    }

    /**
     * Give credits back to the grants this enrollment spent them from. A credit
     * whose grant has expired in the meantime is not returned: it is logged as expired.
     *
     * @return int number of credits returned to the balance
     */
    public function refund(LessonEnrollment $enrollment, int $count, ?LessonOccurrence $occurrence = null, ?User $actor = null): int
    {
        $subscription = $enrollment->subscription;

        if ($subscription === null || $subscription->isUnlimited() || $count <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($enrollment, $subscription, $count, $occurrence, $actor): int {
            $spentPerGrant = CreditTransaction::query()
                ->where('lesson_enrollment_id', $enrollment->id)
                ->whereIn('type', [CreditTransactionType::Consumed, CreditTransactionType::Refunded])
                ->groupBy('credit_grant_id')
                ->havingRaw('SUM(amount) < 0')
                ->selectRaw('credit_grant_id, -SUM(amount) AS spent')
                ->get()
                ->mapWithKeys(fn (CreditTransaction $row) => [(int) $row->credit_grant_id => (int) $row->getAttribute('spent')]);

            $untrackedSpent = $spentPerGrant->pull(0, 0);

            $grants = CreditGrant::query()
                ->whereKey($spentPerGrant->keys())
                ->lockForUpdate()
                ->get()
                ->sortByDesc(fn (CreditGrant $grant) => $grant->expires_at?->getTimestamp() ?? PHP_INT_MAX);

            $left = $count;
            $refunded = 0;

            foreach ($grants->reject->isExpired() as $grant) {
                $returned = min($left, $spentPerGrant[$grant->id]);

                if ($returned === 0) {
                    break;
                }

                $grant->increment('remaining', $returned);
                $subscription->refundCredits($returned);
                $left -= $returned;
                $refunded += $returned;

                $this->record($subscription, CreditTransactionType::Refunded, $returned, null, $grant, $enrollment, $occurrence, $actor);
            }

            $untracked = min($left, $untrackedSpent);

            if ($untracked > 0) {
                $subscription->refundCredits($untracked);
                $left -= $untracked;
                $refunded += $untracked;

                $this->record($subscription, CreditTransactionType::Refunded, $untracked, null, null, $enrollment, $occurrence, $actor);
            }

            foreach ($grants->filter->isExpired() as $grant) {
                $lost = min($left, $spentPerGrant[$grant->id]);

                if ($lost === 0) {
                    break;
                }

                $left -= $lost;

                // One line per credit: it leaves the balance unchanged, so the amount is 0.
                for ($i = 0; $i < $lost; $i++) {
                    $this->record($subscription, CreditTransactionType::Expired, 0, null, $grant, $enrollment, $occurrence, $actor);
                }
            }

            // Spent before the timeline existed: return without a grant, as before.
            if ($left > 0) {
                $subscription->refundCredits($left);
                $refunded += $left;

                $this->record($subscription, CreditTransactionType::Refunded, $left, null, null, $enrollment, $occurrence, $actor);
            }

            return $refunded;
        });
    }

    /**
     * Log credits that stay spent because the member cancelled after the deadline.
     */
    public function forfeit(LessonEnrollment $enrollment, int $count, ?LessonOccurrence $occurrence = null, ?User $actor = null): void
    {
        $subscription = $enrollment->subscription;

        if ($subscription === null || $subscription->isUnlimited() || $count <= 0) {
            return;
        }

        // One line per credit: it was already spent, so the balance does not change.
        for ($i = 0; $i < $count; $i++) {
            $this->record($subscription, CreditTransactionType::Forfeited, 0, null, null, $enrollment, $occurrence, $actor);
        }
    }

    /**
     * Remove the unused credits of a grant whose expiry date has passed.
     */
    public function expire(CreditGrant $grant): void
    {
        DB::transaction(function () use ($grant): void {
            $grant = CreditGrant::query()->whereKey($grant->id)->lockForUpdate()->first();

            if ($grant === null || $grant->remaining === 0 || ! $grant->isExpired()) {
                return;
            }

            $expired = $grant->remaining;
            $grant->update(['remaining' => 0]);

            $subscription = $grant->subscription;

            if ($subscription->isUnlimited()) {
                return;
            }

            $subscription->removeCredits($expired);

            $this->record($subscription, CreditTransactionType::Expired, -$expired, grant: $grant);
        });
    }

    private function record(
        Subscription $subscription,
        CreditTransactionType $type,
        int $amount,
        ?int $balanceAfter = null,
        ?CreditGrant $grant = null,
        ?LessonEnrollment $enrollment = null,
        ?LessonOccurrence $occurrence = null,
        ?User $actor = null,
    ): void {
        CreditTransaction::create([
            'club_id' => $subscription->club_id,
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'credit_grant_id' => $grant?->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => max(0, $balanceAfter ?? (int) $subscription->credits_remaining),
            'lesson_id' => $enrollment->lesson_id ?? $occurrence->lesson_id ?? null,
            'lesson_occurrence_id' => $occurrence->id ?? $enrollment->lesson_occurrence_id ?? null,
            'lesson_enrollment_id' => $enrollment?->id,
            'actor_id' => $actor?->id,
        ]);
    }
}
