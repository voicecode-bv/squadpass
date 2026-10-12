<?php

namespace App\Livewire\SubscriptionPlans;

use App\Enums\BillingInterval;
use App\Models\Club;
use App\Models\SubscriptionPlan;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Subscriptions')]
class Index extends Component
{
    public Club $club;

    /** Set while editing an existing plan; null means the form creates one. */
    public ?int $editingPlanId = null;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $interval = 'monthly';

    public ?int $credits = null;

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    /**
     * Open the form for a new plan.
     */
    public function newPlan(): void
    {
        $this->authorize('manage', $this->club);

        $this->resetValidation();
        $this->resetForm();

        Flux::modal('plan')->show();
    }

    /**
     * Open the form for an existing plan.
     */
    public function editPlan(int $planId): void
    {
        $this->authorize('manage', $this->club);

        $plan = $this->findPlan($planId);

        $this->resetValidation();
        $this->editingPlanId = $plan->id;
        $this->name = $plan->name;
        $this->description = $plan->description ?? '';
        $this->price = number_format($plan->price_cents / 100, 2, '.', '');
        $this->interval = $plan->interval->value;
        $this->credits = $plan->credits;

        Flux::modal('plan')->show();
    }

    public function savePlan(): void
    {
        $this->authorize('manage', $this->club);

        $plan = $this->editingPlanId ? $this->findPlan($this->editingPlanId) : null;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'interval' => ['required', Rule::enum(BillingInterval::class)],
            'credits' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'price_cents' => (int) round($validated['price'] * 100),
            'interval' => $validated['interval'],
            'credits' => $validated['credits'],
        ];

        if ($plan) {
            $plan->update($attributes);
        } else {
            SubscriptionPlan::create(['club_id' => $this->club->id, ...$attributes]);
        }

        $this->resetForm();

        Flux::modal('plan')->close();
        Flux::toast(variant: 'success', text: $plan ? __('Subscription plan updated.') : __('Subscription plan created.'));
    }

    private function resetForm(): void
    {
        $this->reset('editingPlanId', 'name', 'description', 'price', 'credits');
        $this->interval = 'monthly';
    }

    public function toggleActive(int $planId): void
    {
        $this->authorize('manage', $this->club);

        $plan = $this->findPlan($planId);

        $plan->update(['active' => ! $plan->active]);
    }

    /**
     * Plans of other clubs are a 404, never an edit target.
     */
    private function findPlan(int $planId): SubscriptionPlan
    {
        return SubscriptionPlan::query()
            ->where('club_id', $this->club->id)
            ->findOrFail($planId);
    }

    /**
     * @return Collection<int, SubscriptionPlan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return SubscriptionPlan::query()
            ->where('club_id', $this->club->id)
            ->orderBy('price_cents')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.subscription-plans.index');
    }
}
