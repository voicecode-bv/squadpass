<?php

namespace App\Livewire\CheckIns;

use App\Actions\CheckIns\CheckInMember;
use App\Actions\CheckIns\CheckInWithCode;
use App\Enums\LessonOccurrenceStatus;
use App\Enums\StaffPermission;
use App\Exceptions\CheckInException;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\LessonOccurrence;
use App\Models\Membership;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Check-in desk')]
class Desk extends Component
{
    public Club $club;

    public string $search = '';

    public ?int $occurrenceId = null;

    public function mount(Club $club): void
    {
        $this->authorize('access', [$club, StaffPermission::CheckIns]);

        $this->club = $club;
    }

    public function checkIn(int $userId, CheckInMember $checkInMember): void
    {
        $this->authorize('access', [$this->club, StaffPermission::CheckIns]);

        $membership = Membership::query()
            ->where('club_id', $this->club->id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $occurrence = $this->occurrenceId
            ? LessonOccurrence::query()->where('club_id', $this->club->id)->findOrFail($this->occurrenceId)
            : null;

        try {
            $checkIn = $checkInMember->handle($membership->user, $this->club, $occurrence, Auth::user());
        } catch (CheckInException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->todayCheckIns);

        Flux::toast(variant: 'success', text: __(':name checked in (:coverage).', [
            'name' => $membership->user->name,
            'coverage' => $checkIn->coverageLabel(),
        ]));
    }

    /**
     * Check a member in from the QR code in their app, read by the webcam.
     *
     * A scanned check-in is always a free visit, so the lesson picker above
     * deliberately does not apply here.
     */
    public function scanMemberCode(string $code, CheckInWithCode $checkInWithCode): void
    {
        $this->authorize('access', [$this->club, StaffPermission::CheckIns]);

        try {
            $checkIn = $checkInWithCode->fromMemberCode($this->club, $code, Auth::user());
        } catch (CheckInException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->todayCheckIns);

        Flux::toast(variant: 'success', text: __(':name checked in (:coverage).', [
            'name' => $checkIn->user->name,
            'coverage' => $checkIn->coverageLabel(),
        ]));
    }

    /**
     * @return Collection<int, Membership>
     */
    #[Computed]
    public function members(): Collection
    {
        if (mb_strlen($this->search) < 2) {
            return new Collection;
        }

        return Membership::query()
            ->where('club_id', $this->club->id)
            ->active()
            ->whereHas('user', function ($query) {
                $query->where('name', 'like', $this->search.'%')
                    ->orWhere('email', 'like', $this->search.'%');
            })
            ->with('user')
            ->limit(10)
            ->get();
    }

    /**
     * Today's lesson occurrences at this club.
     *
     * @return Collection<int, LessonOccurrence>
     */
    #[Computed]
    public function todayOccurrences(): Collection
    {
        return LessonOccurrence::query()
            ->where('club_id', $this->club->id)
            ->where('status', LessonOccurrenceStatus::Scheduled)
            ->whereBetween('starts_at', [$this->startOfToday(), $this->startOfToday()->addDay()])
            ->with('lesson')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return Collection<int, CheckIn>
     */
    #[Computed]
    public function todayCheckIns(): Collection
    {
        return CheckIn::query()
            ->where('club_id', $this->club->id)
            ->where('checked_in_at', '>=', $this->startOfToday())
            ->with(['user', 'occurrence.lesson'])
            ->latest('checked_in_at')
            ->limit(50)
            ->get();
    }

    /**
     * All check-ins today, for the KPI band; the list itself stops at 50.
     */
    #[Computed]
    public function todayCheckInCount(): int
    {
        return CheckIn::query()
            ->where('club_id', $this->club->id)
            ->where('checked_in_at', '>=', $this->startOfToday())
            ->count();
    }

    public function render(): View
    {
        return view('livewire.check-ins.desk');
    }

    /**
     * Midnight in the club's own timezone, so "today" matches the clock on the wall.
     */
    private function startOfToday(): CarbonInterface
    {
        return now($this->club->timezone)->startOfDay()->utc();
    }
}
