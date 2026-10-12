<?php

namespace App\Livewire;

use App\Models\Club;
use App\Models\Lesson;
use App\Models\Membership;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Search across the clubs the current user manages: clubs, members, lessons
 * and tickets. Every group is scoped to those clubs and capped, so the query
 * stays cheap no matter how big the club is.
 */
class GlobalSearch extends Component
{
    private const MIN_LENGTH = 2;

    private const PER_GROUP = 5;

    public string $query = '';

    public function clear(): void
    {
        $this->reset('query');
    }

    /**
     * Clubs the user manages; superadmins search every club.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function clubIds(): array
    {
        $user = Auth::user();

        return $user->is_superadmin
            ? Club::query()->pluck('id')->all()
            : $user->staffClubs()->pluck('clubs.id')->all();
    }

    /**
     * Grouped results: [['heading' => ..., 'items' => [['key', 'label', 'meta', 'url'], ...]], ...].
     *
     * @return array<int, array{heading: string, items: array<int, array{key: string, label: string, meta: string, url: string}>}>
     */
    #[Computed]
    public function groups(): array
    {
        $term = trim($this->query);

        if (mb_strlen($term) < self::MIN_LENGTH || $this->clubIds === []) {
            return [];
        }

        // Prefix matching keeps the LIKE on the left side of the index.
        $prefix = $term.'%';

        $groups = [
            ['heading' => __('Clubs'), 'items' => $this->clubs($prefix)],
            ['heading' => __('Members'), 'items' => $this->members($prefix)],
            ['heading' => __('Lessons'), 'items' => $this->lessons($prefix)],
            ['heading' => __('Tickets'), 'items' => $this->tickets($term, $prefix)],
        ];

        return array_values(array_filter($groups, fn (array $group) => $group['items'] !== []));
    }

    /**
     * @return array<int, array{key: string, label: string, meta: string, url: string}>
     */
    private function clubs(string $prefix): array
    {
        return Club::query()
            ->whereIn('id', $this->clubIds)
            ->where('name', 'like', $prefix)
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Club $club) => [
                'key' => 'club-'.$club->id,
                'label' => $club->name,
                'meta' => $club->slug,
                'url' => route('clubs.dashboard', $club),
            ])
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, meta: string, url: string}>
     */
    private function members(string $prefix): array
    {
        return Membership::query()
            ->whereIn('club_id', $this->clubIds)
            ->with(['user', 'club'])
            ->whereHas('user', function ($query) use ($prefix) {
                $query->where('name', 'like', $prefix)
                    ->orWhere('email', 'like', $prefix);
            })
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Membership $membership) => [
                'key' => 'membership-'.$membership->id,
                'label' => $membership->user->name,
                'meta' => $membership->club->name.' · #'.$membership->membership_number,
                'url' => route('clubs.members.show', [$membership->club, $membership]),
            ])
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, meta: string, url: string}>
     */
    private function lessons(string $prefix): array
    {
        return Lesson::query()
            ->whereIn('club_id', $this->clubIds)
            ->with('club')
            ->where('name', 'like', $prefix)
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Lesson $lesson) => [
                'key' => 'lesson-'.$lesson->id,
                'label' => $lesson->name,
                'meta' => $lesson->club->name,
                'url' => route('clubs.lessons.edit', [$lesson->club, $lesson]),
            ])
            ->all();
    }

    /**
     * Matches the subject, and the ticket number when the term looks like one.
     *
     * @return array<int, array{key: string, label: string, meta: string, url: string}>
     */
    private function tickets(string $term, string $prefix): array
    {
        $number = (int) preg_replace('/\D/', '', $term);

        return Ticket::query()
            ->whereIn('club_id', $this->clubIds)
            ->with('club')
            ->where(function ($query) use ($prefix, $number) {
                $query->where('subject', 'like', $prefix);

                if ($number > 0) {
                    $query->orWhere('number', $number);
                }
            })
            ->latest()
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'key' => 'ticket-'.$ticket->id,
                'label' => $ticket->subject,
                'meta' => $ticket->reference().' · '.$ticket->club->name,
                'url' => route('clubs.tickets.show', [$ticket->club, $ticket]),
            ])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.global-search');
    }
}
