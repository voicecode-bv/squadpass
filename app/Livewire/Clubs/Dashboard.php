<?php

namespace App\Livewire\Clubs;

use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\Membership;
use App\Models\Payment;
use App\Services\ClubStatistics;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public Club $club;

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    #[Computed]
    public function activeMemberCount(): int
    {
        return Membership::query()
            ->where('club_id', $this->club->id)
            ->where('status', MembershipStatus::Active)
            ->count();
    }

    /**
     * Recurring charges that could not be collected in the past 30 days.
     */
    #[Computed]
    public function failedChargeCount(): int
    {
        return Payment::query()
            ->where('club_id', $this->club->id)
            ->where('metadata->purpose', 'subscription_recurring')
            ->whereIn('status', [PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Canceled])
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();
    }

    #[Computed]
    public function checkInsThisMonth(): int
    {
        return CheckIn::query()
            ->where('club_id', $this->club->id)
            ->where('checked_in_at', '>=', now()->startOfMonth())
            ->count();
    }

    #[Computed]
    public function newMembersLastMonth(): int
    {
        $values = $this->newMembersPerMonth['values'];

        return $values[count($values) - 2] ?? 0;
    }

    #[Computed]
    public function revenueLastMonthCents(): int
    {
        $values = $this->revenuePerMonth['values'];

        return $values[count($values) - 2] ?? 0;
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    #[Computed]
    public function newMembersPerMonth(): array
    {
        return app(ClubStatistics::class)->newMembersPerMonth([$this->club->id]);
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    #[Computed]
    public function activeMembersPerMonth(): array
    {
        return app(ClubStatistics::class)->activeMembersPerMonth([$this->club->id]);
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    #[Computed]
    public function revenuePerMonth(): array
    {
        return app(ClubStatistics::class)->revenuePerMonth([$this->club->id]);
    }

    public function render(): View
    {
        return view('livewire.clubs.dashboard');
    }
}
