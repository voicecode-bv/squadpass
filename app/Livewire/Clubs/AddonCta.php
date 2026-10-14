<?php

namespace App\Livewire\Clubs;

use App\Actions\Addons\PurchaseAddon;
use App\Enums\AddonKey;
use App\Exceptions\PaymentException;
use App\Models\Addon;
use App\Models\Club;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Inviting call to action shown wherever a feature is locked behind an add-on
 * the club does not have yet. Owners can buy it straight from here.
 */
class AddonCta extends Component
{
    #[Locked]
    public Club $club;

    #[Locked]
    public AddonKey $addonKey;

    public function mount(Club $club, AddonKey $addonKey): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
        $this->addonKey = $addonKey;
    }

    public function purchase(PurchaseAddon $purchaseAddon): void
    {
        $this->authorize('update', $this->club);

        $addon = $this->addon ?? abort(404);

        try {
            $checkoutUrl = $purchaseAddon->handle($this->club, $addon, Auth::user());
        } catch (PaymentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->redirect($checkoutUrl);
    }

    /**
     * The add-on for sale, or null when the platform does not sell it right now.
     */
    #[Computed]
    public function addon(): ?Addon
    {
        return Addon::query()->active()->where('key', $this->addonKey->value)->first();
    }

    public function render(): View
    {
        return view('livewire.clubs.addon-cta');
    }
}
