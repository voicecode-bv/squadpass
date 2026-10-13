<?php

namespace App\Livewire\Admin;

use App\Enums\ClubPlan;
use App\Enums\SubscriptionStatus;
use App\Models\ClubPlanSetting;
use App\Models\ClubSubscription;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Superadmin management of the SquadPass plans: monthly price and member limit.
 */
#[Title('SquadPass plans')]
class ClubPlans extends Component
{
    use WithPagination;

    #[Locked]
    public ?string $editingPlan = null;

    public string $price = '';

    public string $member_limit = '';

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
            ->with('club:id,name,slug')
            ->latest('id')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.admin.club-plans', [
            'plans' => ClubPlan::cases(),
        ]);
    }
}
