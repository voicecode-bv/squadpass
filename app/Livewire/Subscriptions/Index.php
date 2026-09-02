<?php

namespace App\Livewire\Subscriptions;

use App\Enums\PaymentStatus;
use App\Models\Club;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

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
     * Recurring subscription charges that could not be collected, newest first.
     *
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function failedCharges(): Collection
    {
        return Payment::query()
            ->where('club_id', $this->club->id)
            ->where('metadata->purpose', 'subscription_recurring')
            ->whereIn('status', [PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Canceled])
            ->with([
                'user',
                'payable' => fn (MorphTo $morphTo) => $morphTo->morphWith([Subscription::class => ['plan']]),
            ])
            ->latest('updated_at')
            ->limit(20)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.subscriptions.index');
    }
}
