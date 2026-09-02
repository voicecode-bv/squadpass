<?php

namespace App\Livewire;

use App\Enums\EnrollmentType;
use App\Enums\LessonOccurrenceStatus;
use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\Payment;
use App\Models\StripCard;
use App\Models\Subscription;
use App\Services\ClubStatistics;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    #[Computed]
    public function greeting(): string
    {
        $hour = now()->hour;

        return match (true) {
            $hour < 6 => __('Good night'),
            $hour < 12 => __('Good morning'),
            $hour < 18 => __('Good afternoon'),
            default => __('Good evening'),
        };
    }

    /**
     * Clubs the user manages, with their active member counts.
     *
     * @return Collection<int, Club>
     */
    #[Computed]
    public function staffClubs(): Collection
    {
        return Auth::user()->staffClubs()
            ->withCount([
                'memberships as active_members_count' => fn ($query) => $query->where('status', MembershipStatus::Active),
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * The next scheduled occurrences this user teaches.
     *
     * @return Collection<int, LessonOccurrence>
     */
    #[Computed]
    public function trainerOccurrences(): Collection
    {
        if (! Auth::user()->trainerProfiles()->exists()) {
            return new Collection;
        }

        return LessonOccurrence::query()
            ->whereHas('lesson.trainers', fn ($query) => $query->where('user_id', Auth::id()))
            ->where('status', LessonOccurrenceStatus::Scheduled)
            ->whereBetween('starts_at', [now(), now()->addWeeks(2)])
            ->with(['lesson.club', 'lesson.room'])
            ->orderBy('starts_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function isMember(): bool
    {
        return Auth::user()->memberships()->exists();
    }

    /**
     * The next one-time enrollments of this user, soonest first.
     *
     * @return Collection<int, LessonEnrollment>
     */
    #[Computed]
    public function upcomingEnrollments(): Collection
    {
        return LessonEnrollment::query()
            ->where('user_id', Auth::id())
            ->where('type', EnrollmentType::Single)
            ->whereHas('occurrence', fn ($query) => $query
                ->where('starts_at', '>=', now())
                ->where('status', LessonOccurrenceStatus::Scheduled))
            ->with(['occurrence.lesson', 'occurrence.club'])
            ->orderBy(
                LessonOccurrence::query()
                    ->select('starts_at')
                    ->whereColumn('lesson_occurrences.id', 'lesson_enrollments.lesson_occurrence_id')
            )
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function stripsRemaining(): int
    {
        return (int) StripCard::query()
            ->where('user_id', Auth::id())
            ->where('strips_remaining', '>', 0)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->sum('strips_remaining');
    }

    #[Computed]
    public function activeSubscriptionCount(): int
    {
        return Subscription::query()
            ->where('user_id', Auth::id())
            ->where('status', SubscriptionStatus::Active)
            ->count();
    }

    #[Computed]
    public function visitsThisMonth(): int
    {
        return CheckIn::query()
            ->where('user_id', Auth::id())
            ->where('checked_in_at', '>=', now()->startOfMonth())
            ->count();
    }

    /**
     * Failed recurring charges of the past 30 days, counted per staff club.
     *
     * @return \Illuminate\Support\Collection<int, int> keyed by club id
     */
    #[Computed]
    public function failedChargesByClub(): \Illuminate\Support\Collection
    {
        $clubIds = $this->staffClubs->modelKeys();

        if ($clubIds === []) {
            return collect();
        }

        return Payment::query()
            ->whereIn('club_id', $clubIds)
            ->where('metadata->purpose', 'subscription_recurring')
            ->whereIn('status', [PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Canceled])
            ->where('updated_at', '>=', now()->subDays(30))
            ->selectRaw('club_id, count(*) as failed_count')
            ->groupBy('club_id')
            ->pluck('failed_count', 'club_id');
    }

    #[Computed]
    public function newMembersLastMonth(): int
    {
        $values = $this->newMembersPerMonth['values'];

        return $values[count($values) - 2] ?? 0;
    }

    /**
     * New memberships per month across the user's staff clubs, oldest month first.
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    #[Computed]
    public function newMembersPerMonth(): array
    {
        return app(ClubStatistics::class)->newMembersPerMonth($this->staffClubs->modelKeys());
    }

    /**
     * Active membership counts measured at the end of each month, oldest first.
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    #[Computed]
    public function activeMembersPerMonth(): array
    {
        return app(ClubStatistics::class)->activeMembersPerMonth($this->staffClubs->modelKeys());
    }

    /**
     * Paid revenue in cents per month across the user's staff clubs, oldest first.
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    #[Computed]
    public function revenuePerMonth(): array
    {
        return app(ClubStatistics::class)->revenuePerMonth($this->staffClubs->modelKeys());
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
