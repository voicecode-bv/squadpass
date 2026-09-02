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

    public string $name = '';

    public int $strips = 10;

    public string $price = '';

    public ?int $validity_months = 12;

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    public function createPlan(): void
    {
        $this->authorize('manage', $this->club);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'strips' => ['required', 'integer', 'min:1', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'validity_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        StripCardPlan::create([
            'club_id' => $this->club->id,
            'name' => $validated['name'],
            'strips' => $validated['strips'],
            'price_cents' => (int) round($validated['price'] * 100),
            'validity_months' => $validated['validity_months'],
        ]);

        $this->reset('name', 'strips', 'price', 'validity_months');
        $this->strips = 10;
        $this->validity_months = 12;

        Flux::modal('create-plan')->close();
        Flux::toast(variant: 'success', text: __('Strip card plan created.'));
    }

    public function toggleActive(int $planId): void
    {
        $this->authorize('manage', $this->club);

        $plan = StripCardPlan::query()
            ->where('club_id', $this->club->id)
            ->findOrFail($planId);

        $plan->update(['active' => ! $plan->active]);
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
