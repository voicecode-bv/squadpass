<?php

namespace App\Livewire\Clubs;

use App\Actions\Addons\CancelAddonSubscription;
use App\Actions\Addons\ChargeAddonSubscription;
use App\Actions\Addons\PurchaseAddon;
use App\Actions\Addons\ResumeAddonSubscription;
use App\Enums\SubscriptionStatus;
use App\Exceptions\PaymentException;
use App\Models\Addon;
use App\Models\AddonPayment;
use App\Models\AddonSubscription;
use App\Models\Club;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Club owners buy, cancel and resume platform add-ons here.
 */
#[Title('Add-ons')]
class Addons extends Component
{
    public Club $club;

    public function mount(Club $club): void
    {
        $this->authorize('update', $club);

        $this->club = $club;
    }

    public function purchase(int $addonId, PurchaseAddon $purchaseAddon): void
    {
        $this->authorize('update', $this->club);

        $addon = Addon::query()->active()->findOrFail($addonId);

        try {
            $checkoutUrl = $purchaseAddon->handle($this->club, $addon, Auth::user());
        } catch (PaymentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->redirect($checkoutUrl);
    }

    public function cancel(int $subscriptionId, CancelAddonSubscription $cancelAddonSubscription): void
    {
        $this->authorize('update', $this->club);

        $cancelAddonSubscription->handle($this->findSubscription($subscriptionId));

        $this->refreshLists();

        Flux::toast(variant: 'success', text: __('Add-on cancelled. It stays active until the end of the paid month.'));
    }

    public function resume(int $subscriptionId, ResumeAddonSubscription $resumeAddonSubscription): void
    {
        $this->authorize('update', $this->club);

        $resumed = $resumeAddonSubscription->handle($this->findSubscription($subscriptionId));

        $this->refreshLists();

        Flux::toast(
            variant: $resumed ? 'success' : 'danger',
            text: $resumed ? __('Add-on resumed. Billing continues after the current month.') : __('This add-on can no longer be resumed. Buy it again instead.'),
        );
    }

    public function retryPayment(int $subscriptionId, ChargeAddonSubscription $chargeAddonSubscription): void
    {
        $this->authorize('update', $this->club);

        $payment = $chargeAddonSubscription->handle($this->findSubscription($subscriptionId), retry: true);

        $this->refreshLists();

        Flux::toast(
            variant: $payment ? 'success' : 'danger',
            text: $payment ? __('A new direct debit has been started. The add-on is reactivated once it is paid.') : __('The payment could not be retried.'),
        );
    }

    /**
     * @return Collection<int, Addon>
     */
    #[Computed]
    public function addons(): Collection
    {
        return Addon::query()->active()->orderBy('id')->get();
    }

    /**
     * The club's most relevant subscription per add-on, keyed by addon id.
     *
     * @return Collection<int, AddonSubscription>
     */
    #[Computed]
    public function subscriptions(): Collection
    {
        return AddonSubscription::query()
            ->where('club_id', $this->club->id)
            ->latest('id')
            ->get()
            ->unique('addon_id')
            ->keyBy('addon_id');
    }

    /**
     * @return Collection<int, AddonPayment>
     */
    #[Computed]
    public function payments(): Collection
    {
        return AddonPayment::query()
            ->where('club_id', $this->club->id)
            ->with('subscription.addon:id,name')
            ->latest('id')
            ->limit(12)
            ->get();
    }

    protected function findSubscription(int $subscriptionId): AddonSubscription
    {
        return AddonSubscription::query()
            ->where('club_id', $this->club->id)
            ->findOrFail($subscriptionId);
    }

    protected function refreshLists(): void
    {
        unset($this->subscriptions, $this->payments);

        $this->club->forgetAddonCache();
    }

    public function render(): View
    {
        return view('livewire.clubs.addons', [
            'statuses' => SubscriptionStatus::class,
        ]);
    }
}
