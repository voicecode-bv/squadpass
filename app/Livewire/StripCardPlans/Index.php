<?php

namespace App\Livewire\StripCardPlans;

use App\Models\Club;
use App\Models\StripCardPlan;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Strip cards')]
class Index extends Component
{
    public Club $club;

    /** Set while editing an existing plan; null means the form creates one. */
    public ?int $editingPlanId = null;

    public string $name = '';

    public int $strips = 10;

    public string $price = '';

    public ?int $validity_months = 12;

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
        $this->strips = $plan->strips;
        $this->price = number_format($plan->price_cents / 100, 2, '.', '');
        $this->validity_months = $plan->validity_months;

        Flux::modal('plan')->show();
    }

    public function savePlan(): void
    {
        $this->authorize('manage', $this->club);

        $plan = $this->editingPlanId ? $this->findPlan($this->editingPlanId) : null;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'strips' => ['required', 'integer', 'min:1', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'validity_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'strips' => $validated['strips'],
            'price_cents' => (int) round($validated['price'] * 100),
            'validity_months' => $validated['validity_months'],
        ];

        if ($plan) {
            $plan->update($attributes);
        } else {
            StripCardPlan::create(['club_id' => $this->club->id, ...$attributes]);
        }

        $this->resetForm();

        Flux::modal('plan')->close();
        Flux::toast(variant: 'success', text: $plan ? __('Strip card plan updated.') : __('Strip card plan created.'));
    }

    private function resetForm(): void
    {
        $this->reset('editingPlanId', 'name', 'strips', 'price', 'validity_months');
        $this->strips = 10;
        $this->validity_months = 12;
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
    private function findPlan(int $planId): StripCardPlan
    {
        return StripCardPlan::query()
            ->where('club_id', $this->club->id)
            ->findOrFail($planId);
    }

    /**
     * @return Collection<int, StripCardPlan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return StripCardPlan::query()
            ->where('club_id', $this->club->id)
            ->orderBy('strips')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.strip-card-plans.index');
    }
}
