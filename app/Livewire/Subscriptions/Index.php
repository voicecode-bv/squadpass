<?php

namespace App\Livewire\Subscriptions;

use App\Models\Club;
use App\Models\Membership;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Computed properties, read as $this->name:
 *
 * @property-read LengthAwarePaginator<int, Subscription> $subscriptions
 */
#[Title('Member subscriptions')]
class Index extends Component
{
    use WithPagination;

    public Club $club;

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    /**
     * @return LengthAwarePaginator<int, Subscription>
     */
    #[Computed]
    public function subscriptions(): LengthAwarePaginator
    {
        return Subscription::query()
            ->where('club_id', $this->club->id)
            ->with(['user', 'plan'])
            ->latest('starts_at')
            ->paginate(25);
    }

    /**
     * Membership id per user on the current page, so names can link
     * to the member page. One query on the (club_id, user_id) unique index.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function membershipIdsByUser(): array
    {
        return Membership::query()
            ->where('club_id', $this->club->id)
            ->whereIn('user_id', $this->subscriptions->pluck('user_id')->unique()->values())
            ->pluck('id', 'user_id')
            ->all();
    }

    public function render(): View
    {
        return view('livewire.subscriptions.index');
    }
}
