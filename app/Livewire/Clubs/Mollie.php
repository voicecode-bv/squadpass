<?php

namespace App\Livewire\Clubs;

use App\Actions\Mollie\ConnectMollieAccount;
use App\Enums\AddonKey;
use App\Enums\PaymentStatus;
use App\Enums\StaffPermission;
use App\Models\Club;
use App\Models\Membership;
use App\Models\Payment;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Computed properties, read as $this->name:
 *
 * @property-read LengthAwarePaginator<int, Payment> $payments
 * @property-read bool $hasAddon
 */
#[Title('Mollie')]
class Mollie extends Component
{
    use WithPagination;

    /**
     * The tabs of the page, in display order.
     *
     * @var list<string>
     */
    public const array TABS = ['connection', 'payments'];

    public Club $club;

    public string $mollie_api_key = '';

    /** Active tab, kept in the URL so paginating or linking keeps the tab. */
    #[Url]
    public string $tab = 'connection';

    /** Payment status filter: empty for all payments, otherwise a statusFilters() key. */
    #[Url]
    public string $status = '';

    /**
     * Every staff member sees the status and payment overview; only those who
     * may manage integrations can change the key.
     */
    public function mount(Club $club): void
    {
        $this->authorize('access', [$club, StaffPermission::Billing]);

        $this->club = $club;

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = self::TABS[0];
        }

        if (! array_key_exists($this->status, self::statusFilters())) {
            $this->status = '';
        }

        // Never ship the key to the browser of someone who may not change it.
        if (Gate::allows('manageIntegrations', $club)) {
            $this->mollie_api_key = $club->mollie_api_key ?? '';
        }
    }

    /**
     * Verify the key with Mollie before storing it.
     */
    public function save(ConnectMollieAccount $connectMollieAccount): void
    {
        $this->ensureCanConfigure();

        $validated = $this->validate([
            'mollie_api_key' => ['required', 'string', 'starts_with:test_,live_'],
        ]);

        if (! $connectMollieAccount->handle($this->club, $validated['mollie_api_key'])) {
            $this->addError('mollie_api_key', __('Mollie did not accept this API key. Copy it again from your Mollie dashboard.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('Mollie connected.'));
    }

    public function disconnect(ConnectMollieAccount $connectMollieAccount): void
    {
        $this->ensureCanConfigure();

        $connectMollieAccount->disconnect($this->club);
        $this->mollie_api_key = '';

        Flux::toast(variant: 'success', text: __('Mollie disconnected.'));
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Status filters for the payment overview; failed covers every way a payment can fall through.
     *
     * @return array<string, list<PaymentStatus>>
     */
    public static function statusFilters(): array
    {
        return [
            '' => [],
            'paid' => [PaymentStatus::Paid],
            'open' => [PaymentStatus::Open],
            'failed' => [PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Canceled],
            'refunded' => [PaymentStatus::Refunded],
        ];
    }

    #[Computed]
    public function hasAddon(): bool
    {
        return $this->club->hasAddon(AddonKey::Mollie);
    }

    #[Computed]
    public function canManageIntegrations(): bool
    {
        return Gate::allows('manageIntegrations', $this->club);
    }

    #[Computed]
    public function failedChargeCount(): int
    {
        return $this->club->recentFailedChargeCount();
    }

    /**
     * Member payments, newest first. Uses the (club_id, created_at) and
     * (club_id, status, created_at) indexes.
     *
     * @return LengthAwarePaginator<int, Payment>
     */
    #[Computed]
    public function payments(): LengthAwarePaginator
    {
        $statuses = self::statusFilters()[$this->status] ?? [];

        return Payment::query()
            ->where('club_id', $this->club->id)
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->with('user')
            ->latest('created_at')
            ->paginate(25);
    }

    /**
     * Membership id per user on the current page, so names link to the member
     * page. One query on the (club_id, user_id) unique index.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function membershipIdsByUser(): array
    {
        return Membership::query()
            ->where('club_id', $this->club->id)
            ->whereIn('user_id', $this->payments->pluck('user_id')->unique()->values())
            ->pluck('id', 'user_id')
            ->all();
    }

    /**
     * Changing the key requires the right to manage integrations and the add-on.
     */
    protected function ensureCanConfigure(): void
    {
        $this->authorize('manageIntegrations', $this->club);

        abort_unless($this->hasAddon, 403);
    }

    public function render(): View
    {
        return view('livewire.clubs.mollie');
    }
}
