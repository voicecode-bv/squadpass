<?php

namespace App\Livewire\Clubs;

use App\Actions\Addons\CalculateAddonUpgrade;
use App\Actions\Addons\CancelAddonSubscription;
use App\Actions\Addons\ChargeAddonSubscription;
use App\Actions\Addons\PurchaseAddon;
use App\Actions\Addons\RefreshPendingAddonPurchase;
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
use Throwable;

/**
 * Club owners buy, cancel and resume platform add-ons here.
 */
#[Title('Add-ons')]
class Addons extends Component
{
    public Club $club;

    public function mount(Club $club, RefreshPendingAddonPurchase $refreshPendingPurchase): void
    {
        $this->authorize('update', $club);

        $this->club = $club;

        $this->refreshPendingPurchases($refreshPendingPurchase);
    }

    /**
     * Returning from (or closing) the Mollie checkout must not leave a purchase stuck
     * on "Waiting for payment" until the webhook arrives: ask Mollie for the state now.
     */
    protected function refreshPendingPurchases(RefreshPendingAddonPurchase $refreshPendingPurchase): void
    {
        AddonSubscription::query()
            ->where('club_id', $this->club->id)
            ->where('status', SubscriptionStatus::Pending)
            ->get()
            ->each(function (AddonSubscription $subscription) use ($refreshPendingPurchase): void {
                try {
                    $refreshPendingPurchase->handle($subscription);
                } catch (Throwable $exception) {
                    // Mollie unreachable: keep the pending state, the webhook settles it later.
                    report($exception);
                }
            });
    }

    public function purchase(int $addonId, PurchaseAddon $purchaseAddon): void
    {
        $this->authorize('manageBilling', $this->club);

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
        $this->authorize('manageBilling', $this->club);

        $cancelAddonSubscription->handle($this->findSubscription($subscriptionId));

        $this->refreshLists();

        Flux::toast(variant: 'success', text: __('Add-on cancelled. It stays active until the end of the paid month.'));
    }

    public function resume(int $subscriptionId, ResumeAddonSubscription $resumeAddonSubscription): void
    {
        $this->authorize('manageBilling', $this->club);

        $resumed = $resumeAddonSubscription->handle($this->findSubscription($subscriptionId));

        $this->refreshLists();

        Flux::toast(
            variant: $resumed ? 'success' : 'danger',
            text: $resumed ? __('Add-on resumed. Billing continues after the current month.') : __('This add-on can no longer be resumed. Buy it again instead.'),
        );
    }

    public function retryPayment(int $subscriptionId, ChargeAddonSubscription $chargeAddonSubscription): void
    {
        $this->authorize('manageBilling', $this->club);

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
        return Addon::query()
            ->active()
            ->with('includedAddons')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Addon $addon): int => $addon->isBundle() ? 1 : 0)
            ->values();
    }

    /**
     * Name of the current bundle that unlocks an add-on, keyed by the add-on's id,
     * so its card says it is included instead of offering it for sale.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function includedVia(): array
    {
        return AddonSubscription::query()
            ->where('club_id', $this->club->id)
            ->with('addon.includedAddons')
            ->get()
            ->filter(fn (AddonSubscription $subscription): bool => $subscription->isCurrent() && $subscription->addon->isBundle())
            ->flatMap(fn (AddonSubscription $subscription): array => $subscription->addon->includedAddons
                ->mapWithKeys(fn (Addon $included): array => [$included->id => $subscription->addon->name])
                ->all())
            ->all();
    }

    /**
     * What buying each bundle would replace and credit, keyed by bundle id.
     * Only bundles that replace at least one current subscription are listed.
     *
     * @return array<int, array{credit_cents: int, replaces: list<string>}>
     */
    #[Computed]
    public function upgrades(): array
    {
        $calculateUpgrade = app(CalculateAddonUpgrade::class);

        return $this->addons
            ->filter(fn (Addon $addon): bool => $addon->isBundle() && ! ($this->subscriptions->get($addon->id)?->isCurrent() ?? false))
            ->mapWithKeys(function (Addon $bundle) use ($calculateUpgrade): array {
                $replaced = $calculateUpgrade->replacedSubscriptions($this->club, $bundle);

                return $replaced->isEmpty() ? [] : [$bundle->id => [
                    'credit_cents' => $calculateUpgrade->creditCents($this->club, $bundle),
                    'replaces' => $replaced->map(fn (AddonSubscription $subscription): string => $subscription->addon->name)->all(),
                ]];
            })
            ->all();
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
        unset($this->subscriptions, $this->payments, $this->includedVia, $this->upgrades);

        $this->club->forgetAddonCache();
    }

    public function render(): View
    {
        return view('livewire.clubs.addons', [
            'statuses' => SubscriptionStatus::class,
        ]);
    }
}
