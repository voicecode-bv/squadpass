<?php

namespace App\Livewire\Clubs;

use App\Enums\StaffPermission;
use App\Models\Club;
use App\Models\LessonEnrollment;
use App\Models\StripCardPlan;
use App\Models\Subscription;
use App\Services\ClubReports;
use App\Services\ClubStatistics;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Club figures over a chosen period: members, churn, revenue, trial conversion and
 * attendance per lesson. Every figure is cached for a few minutes per club and period.
 *
 * @property-read bool $canSeeRevenue
 */
#[Title('Reports')]
class Reports extends Component
{
    /** @var list<int> */
    public const array PERIODS = [3, 6, 12];

    /** Seconds a computed figure is reused before it is recalculated. */
    public const int CACHE_SECONDS = 600;

    #[Locked]
    public Club $club;

    #[Url]
    public int $months = 6;

    public function mount(Club $club): void
    {
        $this->authorize('access', [$club, StaffPermission::Reports]);

        $this->club = $club;

        if (! in_array($this->months, self::PERIODS, true)) {
            $this->months = 6;
        }
    }

    public function updatedMonths(): void
    {
        $this->authorize('access', [$this->club, StaffPermission::Reports]);

        if (! in_array($this->months, self::PERIODS, true)) {
            $this->months = 6;
        }
    }

    #[Computed]
    public function canSeeRevenue(): bool
    {
        return Auth::user()->can('access', [$this->club, StaffPermission::Billing]);
    }

    /**
     * @return array{labels: list<string>, new: list<int>, ended: list<int>, active: list<int>}
     */
    #[Computed]
    public function members(): array
    {
        return $this->cached('members', function (ClubStatistics $statistics): array {
            $new = $statistics->newMembersPerMonth([$this->club->id], $this->months);

            return [
                'labels' => $new['labels'],
                'new' => $new['values'],
                'ended' => $statistics->endedMembersPerMonth([$this->club->id], $this->months)['values'],
                'active' => $statistics->activeMembersPerMonth([$this->club->id], $this->months)['values'],
            ];
        });
    }

    /**
     * Cancelled subscriptions per month and the churn rate against the subscriptions running at the start of the month.
     *
     * @return array{labels: list<string>, cancellations: list<int>, rates: list<float>}
     */
    #[Computed]
    public function churn(): array
    {
        return $this->cached('churn', function (ClubStatistics $statistics): array {
            $cancellations = $statistics->cancellationsPerMonth([$this->club->id], $this->months);
            $running = $statistics->subscriptionsAtMonthStart([$this->club->id], $this->months)['values'];

            return [
                'labels' => $cancellations['labels'],
                'cancellations' => $cancellations['values'],
                'rates' => array_map(
                    fn (int $cancelled, int $base): float => $base > 0 ? round($cancelled / $base * 100, 1) : 0.0,
                    $cancellations['values'],
                    $running,
                ),
            ];
        });
    }

    /**
     * Paid revenue per month in cents, in total and per kind of payment.
     *
     * @return array{labels: list<string>, total: list<int>, subscriptions: int, strip_cards: int, trial_lessons: int}
     */
    #[Computed]
    public function revenue(): array
    {
        abort_unless($this->canSeeRevenue, 403);

        return $this->cached('revenue', function (ClubStatistics $statistics): array {
            $clubIds = [$this->club->id];
            $total = $statistics->revenuePerMonth($clubIds, $this->months);

            return [
                'labels' => $total['labels'],
                'total' => $total['values'],
                'subscriptions' => array_sum($statistics->revenuePerMonth($clubIds, $this->months, Subscription::class)['values']),
                'strip_cards' => array_sum($statistics->revenuePerMonth($clubIds, $this->months, StripCardPlan::class)['values']),
                'trial_lessons' => array_sum($statistics->revenuePerMonth($clubIds, $this->months, LessonEnrollment::class)['values']),
            ];
        });
    }

    #[Computed]
    public function monthlyRecurringRevenueCents(): int
    {
        abort_unless($this->canSeeRevenue, 403);

        return $this->cached('mrr', fn (ClubReports $reports): int => $reports->monthlyRecurringRevenueCents($this->club));
    }

    /**
     * @return array{trials: int, converted: int}
     */
    #[Computed]
    public function trialConversion(): array
    {
        return $this->cached('trials', fn (ClubReports $reports): array => $reports->trialConversion($this->club, $this->periodStart()));
    }

    /**
     * @return list<array{lesson_id: int, name: string, occurrences: int, booked: int, capacity: int, occupancy: float, check_ins: int, no_show_rate: float|null, late_cancellations: int}>
     */
    #[Computed]
    public function lessonAttendance(): array
    {
        return $this->cached('attendance', fn (ClubReports $reports): array => $reports->lessonAttendance($this->club, $this->periodStart()));
    }

    public function render(): View
    {
        return view('livewire.clubs.reports');
    }

    protected function periodStart(): CarbonInterface
    {
        return now()->startOfMonth()->subMonths($this->months - 1);
    }

    /**
     * @template T
     *
     * @param  callable(mixed): T  $compute
     * @return T
     */
    protected function cached(string $metric, callable $compute): mixed
    {
        return Cache::remember(
            "club:{$this->club->id}:reports:{$metric}:{$this->months}",
            self::CACHE_SECONDS,
            fn () => app()->call($compute),
        );
    }
}
