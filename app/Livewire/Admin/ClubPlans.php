<?php

namespace App\Livewire\Admin;

use App\Enums\ClubPlan;
use App\Enums\SubscriptionStatus;
use App\Models\ClubPlanSetting;
use App\Models\ClubSubscription;
use App\Models\Promotion;
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
 * and the promotion that gives the first clubs a lasting discount.
 */
#[Title('SquadPass plans')]
class ClubPlans extends Component
{
    use WithPagination;

    #[Locked]
    public ?string $editingPlan = null;

    public string $price = '';

    public string $member_limit = '';

    public string $promotion_name = '';

    public bool $promotion_active = false;

    public string $promotion_discount = '';

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

    public function editPromotion(): void
    {
        $this->authorize('manage-platform');

        $settings = Promotion::settings();

        $this->promotion_name = $settings['name'];
        $this->promotion_active = $settings['is_active'];
        $this->promotion_discount = (string) $settings['discount_percent'];
        $this->resetErrorBag();

        Flux::modal('edit-promotion')->show();
    }

    /**
     * A changed discount only applies to clubs that take out a plan from now on;
     * clubs with a promotional price keep their discount.
     */
    public function savePromotion(): void
    {
        $this->authorize('manage-platform');

        $validated = $this->validate([
            'promotion_name' => ['required', 'string', 'max:60'],
            'promotion_active' => ['boolean'],
            'promotion_discount' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $promotion = Promotion::query()->firstOrNew();
        $promotion->fill([
            'name' => $validated['promotion_name'],
            'is_active' => $validated['promotion_active'],
            'discount_percent' => (int) $validated['promotion_discount'],
        ])->save();

        unset($this->promotionClaimedCount);

        Flux::modal('edit-promotion')->close();
        Flux::toast(variant: 'success', text: __('Promotion saved.'));
    }

    #[Computed]
    public function promotionClaimedCount(): int
    {
        return Promotion::claimedCount();
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
            ->with('club:id,name,slug,promotion_discount_percent,promotion_claimed_at')
            ->latest('id')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.admin.club-plans', [
            'plans' => ClubPlan::cases(),
            'promotion' => Promotion::settings(),
        ]);
    }
}
