<?php

namespace App\Livewire\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Addon;
use App\Models\AddonSubscription;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Superadmin management of sellable add-ons: naming, pricing and availability.
 * Superadmins also compose bundles here: add-ons without a key of their own
 * that unlock a selection of the single add-ons for one monthly price.
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

    /**
     * Ids of the single add-ons a bundle unlocks.
     *
     * @var list<int|string>
     */
    public array $includedAddonIds = [];

    public function mount(): void
    {
        $this->authorize('manage-platform');

        Addon::syncFromKeys();
    }

    /**
     * Open the shared modal for a new bundle. Single add-ons are never created
     * here: they follow from AddonKey.
     */
    public function createBundle(): void
    {
        $this->authorize('manage-platform');

        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->price = '';
        $this->is_active = false;
        $this->includedAddonIds = [];
        $this->resetErrorBag();

        Flux::modal('edit-addon')->show();
    }

    public function edit(int $addonId): void
    {
        $this->authorize('manage-platform');

        $addon = Addon::query()->with('includedAddons')->findOrFail($addonId);

        $this->editingId = $addon->id;
        $this->name = $addon->name;
        $this->description = $addon->description ?? '';
        $this->price = number_format($addon->price_cents / 100, 2, '.', '');
        $this->is_active = $addon->is_active;
        $this->includedAddonIds = $addon->includedAddons->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        $this->resetErrorBag();

        Flux::modal('edit-addon')->show();
    }

    public function save(): void
    {
        $this->authorize('manage-platform');

        $addon = $this->editingId === null ? null : Addon::query()->findOrFail($this->editingId);
        $isBundle = $addon === null || $addon->isBundle();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
        ];

        if ($isBundle) {
            $rules['includedAddonIds'] = ['required', 'array', 'min:2'];
            $rules['includedAddonIds.*'] = ['integer', Rule::exists('addons', 'id')->whereNotNull('key')];
        }

        $validated = $this->validate($rules, [
            'includedAddonIds.min' => __('A bundle combines at least two add-ons.'),
            'includedAddonIds.required' => __('A bundle combines at least two add-ons.'),
        ]);

        $attributes = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'price_cents' => (int) round($validated['price'] * 100),
            'is_active' => $validated['is_active'],
        ];

        if ($addon === null) {
            $addon = Addon::create(['key' => null, ...$attributes]);
        } else {
            $addon->update($attributes);
        }

        if ($isBundle) {
            $addon->includedAddons()->sync(array_map('intval', $validated['includedAddonIds']));
        }

        unset($this->addons);

        Addon::forgetLandingCache();

        Flux::modal('edit-addon')->close();
        Flux::toast(variant: 'success', text: $isBundle ? __('Bundle saved.') : __('Add-on saved.'));
    }

    /**
     * Whether the modal is editing (or creating) a bundle.
     */
    #[Computed]
    public function editingBundle(): bool
    {
        return $this->editingId === null || $this->addons->firstWhere('id', $this->editingId)?->isBundle() === true;
    }

    /**
     * The single add-ons a bundle can combine.
     *
     * @return Collection<int, Addon>
     */
    #[Computed]
    public function singleAddons(): Collection
    {
        return $this->addons->reject(fn (Addon $addon): bool => $addon->isBundle())->values();
    }

    /**
     * @return Collection<int, Addon>
     */
    #[Computed]
    public function addons(): Collection
    {
        return Addon::query()
            ->with('includedAddons')
            ->withCount([
                'subscriptions as active_subscriptions_count' => fn ($query) => $query->where('status', SubscriptionStatus::Active),
            ])
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Addon $addon): int => $addon->isBundle() ? 1 : 0)
            ->values();
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
