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

    public function createPlan(): void
    {
        $this->authorize('manage', $this->club);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'interval' => ['required', Rule::enum(BillingInterval::class)],
            'credits' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        SubscriptionPlan::create([
            'club_id' => $this->club->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'price_cents' => (int) round($validated['price'] * 100),
            'interval' => $validated['interval'],
            'credits' => $validated['credits'],
        ]);

        $this->reset('name', 'description', 'price', 'credits');
        $this->interval = 'monthly';

        Flux::modal('create-plan')->close();
        Flux::toast(variant: 'success', text: __('Subscription plan created.'));
    }

    public function toggleActive(int $planId): void
    {
        $this->authorize('manage', $this->club);

        $plan = SubscriptionPlan::query()
            ->where('club_id', $this->club->id)
            ->findOrFail($planId);

        $plan->update(['active' => ! $plan->active]);
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
