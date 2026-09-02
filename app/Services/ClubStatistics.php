<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Membership;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Monthly statistics for one or more clubs, aggregated in single scoped queries.
 */
class ClubStatistics
{
    /**
     * New memberships per month, oldest month first.
     *
     * @param  list<int>  $clubIds
     * @return array{labels: list<string>, values: list<int>}
     */
    public function newMembersPerMonth(array $clubIds): array
    {
        return $this->monthlySeries(
            $clubIds,
            Membership::query(),
            fn (int $index) => "SUM(CASE WHEN joined_at >= ? AND joined_at < ? THEN 1 ELSE 0 END) as m{$index}",
        );
    }

    /**
     * Active membership counts measured at the end of each month, oldest first.
     *
     * @param  list<int>  $clubIds
     * @return array{labels: list<string>, values: list<int>}
     */
    public function activeMembersPerMonth(array $clubIds): array
    {
        return $this->monthlySeries(
            $clubIds,
            Membership::query(),
            fn (int $index) => "SUM(CASE WHEN joined_at < ? AND (ended_at IS NULL OR ended_at >= ?) THEN 1 ELSE 0 END) as m{$index}",
            fn (array $bucket) => [$bucket['end'], $bucket['end']],
        );
    }

    /**
     * Paid revenue in cents per month, oldest first.
     *
     * @param  list<int>  $clubIds
     * @return array{labels: list<string>, values: list<int>}
     */
    public function revenuePerMonth(array $clubIds): array
    {
        return $this->monthlySeries(
            $clubIds,
            Payment::query()->where('status', PaymentStatus::Paid),
            fn (int $index) => "SUM(CASE WHEN paid_at >= ? AND paid_at < ? THEN amount_cents ELSE 0 END) as m{$index}",
        );
    }

    /**
     * Aggregate one SUM(CASE ...) column per month in a single scoped query.
     *
     * @param  list<int>  $clubIds
     * @param  Builder<Membership>|Builder<Payment>  $query
     * @param  callable(int): string  $caseFor
     * @param  callable(array{label: string, start: string, end: string}): array{string, string}|null  $bindingsFor
     * @return array{labels: list<string>, values: list<int>}
     */
    protected function monthlySeries(array $clubIds, Builder $query, callable $caseFor, ?callable $bindingsFor = null): array
    {
        $buckets = $this->monthBuckets();
        $labels = array_column($buckets, 'label');

        if ($clubIds === []) {
            return ['labels' => $labels, 'values' => array_fill(0, count($buckets), 0)];
        }

        $selects = [];
        $bindings = [];

        foreach ($buckets as $index => $bucket) {
            $selects[] = $caseFor($index);
            array_push($bindings, ...($bindingsFor ? $bindingsFor($bucket) : [$bucket['start'], $bucket['end']]));
        }

        $row = $query
            ->whereIn('club_id', $clubIds)
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();

        return [
            'labels' => $labels,
            'values' => array_map(fn (int $index) => (int) ($row?->{"m{$index}"} ?? 0), array_keys($buckets)),
        ];
    }

    /**
     * The trailing twelve calendar months, oldest first, with exclusive end dates.
     *
     * @return list<array{label: string, start: string, end: string}>
     */
    protected function monthBuckets(): array
    {
        return collect(range(11, 0))
            ->map(function (int $offset) {
                $start = now()->startOfMonth()->subMonths($offset);

                return [
                    'label' => $start->isoFormat('MMM'),
                    'start' => $start->toDateString(),
                    'end' => $start->copy()->addMonth()->toDateString(),
                ];
            })
            ->all();
    }
}
