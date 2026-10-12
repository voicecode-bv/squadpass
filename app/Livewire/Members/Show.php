<?php

namespace App\Livewire\Members;

use App\Actions\Moneybird\SyncMemberContact;
use App\Enums\FormAudience;
use App\Enums\SubscriptionStatus;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\StripCard;
use App\Models\Subscription;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Member')]
class Show extends Component
{
    public Club $club;

    public Membership $membership;

    public function mount(Club $club, Membership $membership): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
        $this->membership = $club->memberships()->with('user')->findOrFail($membership->id);
    }

    public function syncMoneybird(SyncMemberContact $syncMemberContact): void
    {
        $this->authorize('manage', $this->club);

        if (! $this->club->hasMoneybirdConfigured()) {
            Flux::toast(variant: 'danger', text: __('Moneybird is not configured for this club.'));

            return;
        }

        $syncMemberContact->handle($this->membership);

        $this->membership = $this->club->memberships()->with('user')->findOrFail($this->membership->id);

        Flux::toast(variant: 'success', text: __('Member synced with Moneybird.'));
    }

    #[Computed]
    public function activeSubscriptionCount(): int
    {
        return Subscription::query()
            ->where('club_id', $this->club->id)
            ->where('user_id', $this->membership->user_id)
            ->where('status', SubscriptionStatus::Active)
            ->count();
    }

    #[Computed]
    public function stripsRemaining(): int
    {
        return (int) StripCard::query()
            ->where('club_id', $this->club->id)
            ->where('user_id', $this->membership->user_id)
            ->where('strips_remaining', '>', 0)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->sum('strips_remaining');
    }

    #[Computed]
    public function checkInsThisMonth(): int
    {
        return CheckIn::query()
            ->where('club_id', $this->club->id)
            ->where('user_id', $this->membership->user_id)
            ->where('checked_in_at', '>=', now()->startOfMonth())
            ->count();
    }

    /**
     * @return Collection<int, Subscription>
     */
    #[Computed]
    public function subscriptions(): Collection
    {
        return Subscription::query()
            ->where('club_id', $this->club->id)
            ->where('user_id', $this->membership->user_id)
            ->with('plan')
            ->latest('starts_at')
            ->get();
    }

    /**
     * @return Collection<int, StripCard>
     */
    #[Computed]
    public function stripCards(): Collection
    {
        return StripCard::query()
            ->where('club_id', $this->club->id)
            ->where('user_id', $this->membership->user_id)
            ->with('plan')
            ->latest('purchased_at')
            ->get();
    }

    /**
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function payments(): Collection
    {
        return Payment::query()
            ->where('club_id', $this->club->id)
            ->where('user_id', $this->membership->user_id)
            ->latest('created_at')
            ->limit(15)
            ->get();
    }

    /**
     * Published forms this member can fill in for this club.
     *
     * @return Collection<int, Form>
     */
    #[Computed]
    public function forms(): Collection
    {
        return Form::query()
            ->where('club_id', $this->club->id)
            ->published()
            ->where(fn ($query) => $query
                ->where('audience', FormAudience::AllMembers->value)
                ->orWhereHas('assignments', fn ($assignment) => $assignment->where('user_id', $this->membership->user_id)))
            ->orderBy('name')
            ->get();
    }

    /**
     * The member's latest response per form, keyed by form id.
     *
     * @return Collection<int, FormSubmission>
     */
    #[Computed]
    public function formSubmissions(): Collection
    {
        return FormSubmission::query()
            ->where('user_id', $this->membership->user_id)
            ->where('is_current', true)
            ->whereIn('form_id', $this->forms->modelKeys())
            ->get()
            ->keyBy('form_id');
    }

    /**
     * @return Collection<int, CheckIn>
     */
    #[Computed]
    public function checkIns(): Collection
    {
        return CheckIn::query()
            ->where('club_id', $this->club->id)
            ->where('user_id', $this->membership->user_id)
            ->with('occurrence.lesson')
            ->latest('checked_in_at')
            ->limit(15)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.members.show');
    }
}
