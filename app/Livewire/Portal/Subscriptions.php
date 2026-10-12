<?php

namespace App\Livewire\Portal;

use App\Actions\Payments\CancelSubscription;
use App\Actions\Payments\StartSubscription;
use App\Enums\MembershipStatus;
use App\Exceptions\PaymentException;
use App\Models\Club;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('My subscriptions')]
class Subscriptions extends Component
{
    public function subscribe(int $planId, StartSubscription $startSubscription): void
    {
        $plan = SubscriptionPlan::query()
            ->active()
            ->with('club')
            ->findOrFail($planId);

        try {
            $checkoutUrl = $startSubscription->handle(Auth::user(), $plan);
        } catch (PaymentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->redirect($checkoutUrl);
    }

    public function cancel(int $subscriptionId, CancelSubscription $cancelSubscription): void
    {
        $subscription = Subscription::query()
            ->where('user_id', Auth::id())
            ->findOrFail($subscriptionId);

        $cancelSubscription->handle($subscription);

        unset($this->subscriptions, $this->availablePlans, $this->payableClubIds);

        Flux::toast(variant: 'success', text: __('Subscription cancelled. You keep access until the end of the paid period.'));
    }

    /**
     * @return Collection<int, Subscription>
     */
    #[Computed]
    public function subscriptions(): Collection
    {
        return Subscription::query()
            ->where('user_id', Auth::id())
            ->with(['club', 'plan'])
            ->latest('starts_at')
            ->get();
    }

    /**
     * Active plans at clubs where the member has no current subscription.
     *
     * @return Collection<int, SubscriptionPlan>
     */
    #[Computed]
    public function availablePlans(): Collection
    {
        $clubIds = Auth::user()->memberships()
            ->where('status', MembershipStatus::Active)
            ->pluck('club_id');

        $blockedClubIds = $this->subscriptions()
            ->filter(fn (Subscription $subscription) => $subscription->blocksNewSubscription())
            ->pluck('club_id');

        return SubscriptionPlan::query()
            ->active()
            ->whereIn('club_id', $clubIds)
            ->whereNotIn('club_id', $blockedClubIds)
            ->with('club')
            ->orderBy('club_id')
            ->orderBy('price_cents')
            ->get();
    }

    /**
     * Clubs from the offered plans that can actually take an online payment.
     * Without it the member would only find out after being sent to checkout.
     *
     * @return array<int, true>
     */
    #[Computed]
    public function payableClubIds(): array
    {
        return $this->availablePlans
            ->pluck('club')
            ->unique('id')
            ->filter(fn (Club $club): bool => $club->acceptsOnlinePayments())
            ->mapWithKeys(fn (Club $club): array => [$club->id => true])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.portal.subscriptions');
    }
}
