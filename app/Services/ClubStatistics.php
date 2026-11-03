<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;

/**
 * Monthly statistics for one or more clubs, aggregated in single scoped queries.
 */
class ClubStatistics
{
    /**
     * Result column per month bucket, oldest first. Fixed names keep the
     * generated SELECT a literal string.
     *
     * @var list<literal-string>
     */
    protected const array MONTH_COLUMNS = ['m0', 'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11'];

    /**
     * New memberships per month, oldest month first.
     *
     * @param  list<int>  $clubIds
     * @return array{labels: list<string>, values: list<int>}
     */
    public function newMembersPerMonth(array $clubIds, int $months = 12): array
    {
        return $this->monthlySeries(
            $clubIds,
            Membership::query()->where('joined_at', '>=', $this->periodStart($months)),
            'SUM(CASE WHEN joined_at >= ? AND joined_at < ? THEN 1 ELSE 0 END)',
            months: $months,
        );
    }

    /**
     * Memberships that ended per month, oldest month first.
     *
     * @param  list<int>  $clubIds
     * @return array{labels: list<string>, values: list<int>}
     */
    public function endedMembersPerMonth(array $clubIds, int $months = 12): array
    {
        return $this->monthlySeries(
            $clubIds,
            Membership::query()->where('ended_at', '>=', $this->periodStart($months)),
            'SUM(CASE WHEN ended_at >= ? AND ended_at < ? THEN 1 ELSE 0 END)',
            months: $months,
        );
    }

    /**
     * Active membership counts measured at the end of each month, oldest first.
     *
     * @param  list<int>  $clubIds
     * @return array{labels: list<string>, values: list<int>}
     */
    public function activeMembersPerMonth(array $clubIds, int $months = 12): array
    {
        return $this->monthlySeries(
            $clubIds,
            // Someone on a trial lesson is not a member yet; converting sets joined_at to that day.
            Membership::query()->where('status', '!=', MembershipStatus::Trial),
            'SUM(CASE WHEN joined_at < ? AND (ended_at IS NULL OR ended_at >= ?) THEN 1 ELSE 0 END)',
            fn (array $bucket) => [$bucket['end'], $bucket['end']],
            $months,
        );
    }

    /**
     * Paid revenue in cents per month, oldest first.
     *
     * @param  list<int>  $clubIds
     * @return array{labels: list<string>, values: list<int>}
     */
    public function revenuePerMonth(array $clubIds, int $months = 12, ?string $payableType = null): array
    {
        return $this->monthlySeries(
            $clubIds,
            Payment::query()
                ->where('status', PaymentStatus::Paid)
                ->where('paid_at', '>=', $this->periodStart($months))
                ->when($payableType, fn (Builder $query) => $query->where('payable_type', $payableType)),
            'SUM(CASE WHEN paid_at >= ? AND paid_at < ? THEN amount_cents ELSE 0 END)',
            months: $months,
        );
    }

    /**
     * Subscriptions cancelled per month, oldest first.
     *
     * @param  list<int>  $clubIds
     * @return array{labels: list<string>, values: list<int>}
     */
    public function cancellationsPerMonth(array $clubIds, int $months = 12): array
    {
        return $this->monthlySeries(
            $clubIds,
            Subscription::query()->where('cancelled_at', '>=', $this->periodStart($months)),
            'SUM(CASE WHEN cancelled_at >= ? AND cancelled_at < ? THEN 1 ELSE 0 END)',
            months: $months,
        );
    }

    /**
     * Subscriptions running at the start of each month (started before it and not
     * cancelled yet), the base for the churn rate. Oldest first.
     *
     * @param  list<int>  $clubIds
     * @return array{labels: list<string>, values: list<int>}
     */
    public function subscriptionsAtMonthStart(array $clubIds, int $months = 12): array
    {
        return $this->monthlySeries(
            $clubIds,
            Subscription::query()->where('status', '!=', SubscriptionStatus::Pending),
            'SUM(CASE WHEN starts_at < ? AND (cancelled_at IS NULL OR cancelled_at >= ?) THEN 1 ELSE 0 END)',
            fn (array $bucket) => [$bucket['start'], $bucket['start']],
            $months,
        );
    }

    /**
     * Aggregate one SUM(CASE ...) column per month in a single scoped query.
     *
     * @param  list<int>  $clubIds
     * @param  Builder<Membership>|Builder<Payment>|Builder<Subscription>  $query
     * @param  literal-string  $sumCase  aggregate per month, with two placeholders for the month's bindings
     * @param  callable(array{label: string, start: string, end: string}): array{string, string}|null  $bindingsFor
     * @param  int  $months  number of trailing months, at most 12
     * @return array{labels: list<string>, values: list<int>}
     */
    protected function monthlySeries(array $clubIds, Builder $query, string $sumCase, ?callable $bindingsFor = null, int $months = 12): array
    {
        $buckets = $this->monthBuckets($months);
        $labels = array_column($buckets, 'label');

        if ($clubIds === []) {
            return ['labels' => $labels, 'values' => array_fill(0, count($buckets), 0)];
        }

        $selects = [];
        $bindings = [];

        foreach ($buckets as $index => $bucket) {
            $selects[] = $sumCase.' as '.self::MONTH_COLUMNS[$index];
            array_push($bindings, ...($bindingsFor ? $bindingsFor($bucket) : [$bucket['start'], $bucket['end']]));
        }

        $row = $query
            ->whereIn('club_id', $clubIds)
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();

        return [
            'labels' => $labels,
            'values' => array_map(fn (int $index): int => (int) ($row->{self::MONTH_COLUMNS[$index]} ?? 0), array_keys($buckets)),
        ];
    }

    /**
     * The first day of the oldest month in the period, to bound the scanned rows.
     */
    protected function periodStart(int $months): string
    {
        return now()->startOfMonth()->subMonths($this->clampMonths($months) - 1)->toDateString();
    }

    protected function clampMonths(int $months): int
    {
        return max(1, min($months, count(self::MONTH_COLUMNS)));
    }

    /**
     * The trailing calendar months, oldest first, with exclusive end dates.
     *
     * @return list<array{label: string, start: string, end: string}>
     */
    protected function monthBuckets(int $months = 12): array
    {
        return array_map(
            function (int $offset): array {
                $start = now()->startOfMonth()->subMonths($offset);

                return [
                    'label' => $start->isoFormat('MMM'),
                    'start' => $start->toDateString(),
                    'end' => $start->copy()->addMonth()->toDateString(),
                ];
            },
            range($this->clampMonths($months) - 1, 0),
        );
    }
}
