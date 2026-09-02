<?php

namespace App\Livewire\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Addon;
use App\Models\AddonSubscription;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Superadmin management of sellable add-ons: naming, pricing and availability.
 */
#[Title('Add-ons')]
class Addons extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public bool $is_active = false;

    public function mount(): void
    {
        $this->authorize('manage-platform');

        Addon::syncFromKeys();
    }

    public function edit(int $addonId): void
    {
        $this->authorize('manage-platform');

        $addon = Addon::query()->findOrFail($addonId);

        $this->editingId = $addon->id;
        $this->name = $addon->name;
        $this->description = $addon->description ?? '';
        $this->price = number_format($addon->price_cents / 100, 2, '.', '');
        $this->is_active = $addon->is_active;
        $this->resetErrorBag();

        Flux::modal('edit-addon')->show();
    }

    public function save(): void
    {
        $this->authorize('manage-platform');

        $addon = Addon::query()->findOrFail($this->editingId);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
        ]);

        $addon->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'price_cents' => (int) round($validated['price'] * 100),
            'is_active' => $validated['is_active'],
        ]);

        unset($this->addons);

        Flux::modal('edit-addon')->close();
        Flux::toast(variant: 'success', text: __('Add-on saved.'));
    }

    /**
     * @return Collection<int, Addon>
     */
    #[Computed]
    public function addons(): Collection
    {
        return Addon::query()
            ->withCount([
                'subscriptions as active_subscriptions_count' => fn ($query) => $query->where('status', SubscriptionStatus::Active),
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return LengthAwarePaginator<int, AddonSubscription>
     */
    #[Computed]
    public function subscriptions(): LengthAwarePaginator
    {
        return AddonSubscription::query()
            ->with(['club:id,name,slug', 'addon:id,name'])
            ->latest('id')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.admin.addons');
    }
}
