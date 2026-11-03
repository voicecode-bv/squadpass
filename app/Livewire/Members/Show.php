<?php

namespace App\Livewire\Members;

use App\Actions\Moneybird\SyncMemberContact;
use App\Enums\FormAudience;
use App\Enums\SubscriptionStatus;
use App\Exceptions\MoneybirdContactConflict;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\CreditTransaction;
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
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Computed properties, read as $this->name:
 *
 * @property-read Collection<int, Form> $forms
 * @property-read Collection<int, CreditTransaction> $creditTransactions
 * @property-read array{total: int, limited: int, remaining: int} $activeSubscriptionTotals
 */
#[Title('Member')]
class Show extends Component
{
    public Club $club;

    public Membership $membership;

    /**
     * The tabs of the member page, in display order.
     *
     * @var list<string>
     */
    public const array TABS = ['membership', 'subscriptions', 'credits', 'payments', 'forms', 'check-ins'];

    /** Page size of the credit timeline. */
    public const int CREDIT_TIMELINE_PAGE = 50;

    /** Active tab, kept in the URL so a tab can be linked to directly. */
    #[Url]
    public string $tab = 'membership';

    /** How many credit timeline lines are shown; grows with "Show more". */
    public int $creditTimelineLimit = self::CREDIT_TIMELINE_PAGE;

    public function mount(Club $club, Membership $membership): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
        $this->membership = $club->memberships()->with('user')->findOrFail($membership->id);

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = self::TABS[0];
        }
    }

    public function syncMoneybird(SyncMemberContact $syncMemberContact): void
    {
        $this->authorize('manage', $this->club);

        if (! $this->club->hasMoneybirdConfigured()) {
            Flux::toast(variant: 'danger', text: __('Moneybird is not configured for this club.'));

            return;
        }

        try {
            $syncMemberContact->handle($this->membership);
        } catch (MoneybirdContactConflict $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->membership = $this->club->memberships()->with('user')->findOrFail($this->membership->id);

        Flux::toast(variant: 'success', text: __('Member synced with Moneybird.'));
    }

    #[Computed]
    public function activeSubscriptionCount(): int
    {
        return $this->activeSubscriptionTotals['total'];
    }

    /**
     * Credits left across the member's active subscriptions; null when one of them is unlimited.
     */
    #[Computed]
    public function creditsRemaining(): ?int
    {
        $totals = $this->activeSubscriptionTotals;

        return $totals['limited'] < $totals['total'] ? null : $totals['remaining'];
    }

    /**
     * Aggregates the member's active subscriptions in a single query.
     *
     * @return array{total: int, limited: int, remaining: int}
     */
    #[Computed]
    public function activeSubscriptionTotals(): array
    {
        $totals = Subscription::query()
            ->where('club_id', $this->club->id)
            ->where('user_id', $this->membership->user_id)
            ->where('status', SubscriptionStatus::Active)
            ->toBase()
            ->selectRaw('COUNT(*) AS total, COUNT(credits_remaining) AS limited, COALESCE(SUM(credits_remaining), 0) AS remaining')
            ->first();

        return [
            'total' => (int) $totals->total,
            'limited' => (int) $totals->limited,
            'remaining' => (int) $totals->remaining,
        ];
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

    public function showMoreCreditTransactions(): void
    {
        $this->authorize('manage', $this->club);

        $this->creditTimelineLimit += self::CREDIT_TIMELINE_PAGE;
    }

    /**
     * The newest lines of the member's credit timeline, one extra to know whether there are more.
     *
     * @return Collection<int, CreditTransaction>
     */
    #[Computed]
    public function creditTransactions(): Collection
    {
        return CreditTransaction::query()
            ->where('club_id', $this->club->id)
            ->where('user_id', $this->membership->user_id)
            ->with(['club:id,name,timezone', 'lesson:id,name', 'occurrence:id,starts_at', 'actor:id,name'])
            ->orderByDesc('id')
            ->limit($this->creditTimelineLimit + 1)
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
