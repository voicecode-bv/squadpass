<?php

namespace App\Livewire\Admin;

use App\Enums\ClubPlan;
use App\Enums\SubscriptionStatus;
use App\Models\ClubPlanSetting;
use App\Models\ClubSubscription;
use App\Models\LaunchOffer;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Superadmin management of the SquadPass plans: monthly price and member limit,
 * and the opening offer that gives the first clubs a lasting founding price.
 */
#[Title('SquadPass plans')]
class ClubPlans extends Component
{
    use WithPagination;

    #[Locked]
    public ?string $editingPlan = null;

    public string $price = '';

    public string $member_limit = '';

    public bool $offer_active = false;

    public string $offer_discount_percent = '';

    public string $offer_spots = '';

    public function mount(): void
    {
        $this->authorize('manage-platform');
    }

    public function edit(string $plan): void
    {
        $this->authorize('manage-platform');

        $clubPlan = ClubPlan::from($plan);
        $priceCents = $clubPlan->priceCents();

        $this->editingPlan = $clubPlan->value;
        $this->price = $priceCents === null ? '' : number_format($priceCents / 100, 2, '.', '');
        $this->member_limit = (string) $clubPlan->memberLimit();
        $this->resetErrorBag();

        Flux::modal('edit-club-plan')->show();
    }

    public function save(): void
    {
        $this->authorize('manage-platform');

        $clubPlan = ClubPlan::from((string) $this->editingPlan);

        $validated = $this->validate([
            'price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'member_limit' => ['nullable', 'integer', 'min:1', 'max:10000000'],
        ]);

        ClubPlanSetting::query()->updateOrCreate(['plan' => $clubPlan], [
            'price_cents' => blank($validated['price']) ? null : (int) round((float) $validated['price'] * 100),
            'member_limit' => blank($validated['member_limit']) ? null : (int) $validated['member_limit'],
        ]);

        Flux::modal('edit-club-plan')->close();
        Flux::toast(variant: 'success', text: __('Plan saved.'));
    }

    public function editOffer(): void
    {
        $this->authorize('manage-platform');

        $settings = LaunchOffer::settings();

        $this->offer_active = $settings['is_active'];
        $this->offer_discount_percent = (string) $settings['discount_percent'];
        $this->offer_spots = (string) $settings['spots'];
        $this->resetErrorBag();

        Flux::modal('edit-launch-offer')->show();
    }

    /**
     * A changed discount only applies to clubs that claim a spot from now on;
     * founding clubs keep the discount they claimed.
     */
    public function saveOffer(): void
    {
        $this->authorize('manage-platform');

        $validated = $this->validate([
            'offer_active' => ['boolean'],
            'offer_discount_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'offer_spots' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $offer = LaunchOffer::query()->firstOrNew();
        $offer->fill([
            'is_active' => $validated['offer_active'],
            'discount_percent' => (int) $validated['offer_discount_percent'],
            'spots' => (int) $validated['offer_spots'],
        ])->save();

        unset($this->foundingSpotsTaken);

        Flux::modal('edit-launch-offer')->close();
        Flux::toast(variant: 'success', text: __('Opening offer saved.'));
    }

    #[Computed]
    public function foundingSpotsTaken(): int
    {
        return LaunchOffer::spotsTaken();
    }

    /**
     * Active subscriptions per plan value.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function activeSubscriptionCounts(): array
    {
        return ClubSubscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->selectRaw('plan, count(*) as aggregate')
            ->groupBy('plan')
            ->pluck('aggregate', 'plan')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, ClubSubscription>
     */
    #[Computed]
    public function subscriptions(): LengthAwarePaginator
    {
        return ClubSubscription::query()
            ->with('club:id,name,slug,founding_discount_percent,founding_claimed_at')
            ->latest('id')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.admin.club-plans', [
            'plans' => ClubPlan::cases(),
            'launchOffer' => LaunchOffer::settings(),
        ]);
    }
}
