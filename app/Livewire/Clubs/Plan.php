<?php

namespace App\Livewire\Clubs;

use App\Actions\ClubSubscriptions\ActivateManualClubPlan;
use App\Actions\ClubSubscriptions\CancelClubSubscription;
use App\Actions\ClubSubscriptions\ChangeClubPlan;
use App\Actions\ClubSubscriptions\ChargeClubSubscription;
use App\Actions\ClubSubscriptions\ResumeClubSubscription;
use App\Actions\ClubSubscriptions\StartClubSubscription;
use App\Enums\ClubPlan;
use App\Enums\SubscriptionStatus;
use App\Exceptions\ClubPlanException;
use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\ClubSubscriptionPayment;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Club owners take out, switch, cancel and resume their SquadPass plan here.
 */
#[Title('SquadPass plan')]
class Plan extends Component
{
    public Club $club;

    public function mount(Club $club): void
    {
        $this->authorize('update', $club);

        $this->club = $club;
    }

    public function subscribe(string $plan, StartClubSubscription $startClubSubscription): void
    {
        $this->authorize('update', $this->club);

        try {
            $checkoutUrl = $startClubSubscription->handle($this->club, ClubPlan::from($plan), Auth::user());
        } catch (ClubPlanException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->redirect($checkoutUrl);
    }

    public function changePlan(string $plan, ChangeClubPlan $changeClubPlan): void
    {
        $this->authorize('update', $this->club);

        $subscription = $this->latestSubscriptionOrFail();

        try {
            $changeClubPlan->handle($subscription, ClubPlan::from($plan));
        } catch (ClubPlanException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->refreshState();

        Flux::toast(variant: 'success', text: __('Plan changed. The new price applies from the next billing date.'));
    }

    public function cancel(CancelClubSubscription $cancelClubSubscription): void
    {
        $this->authorize('update', $this->club);

        $cancelClubSubscription->handle($this->latestSubscriptionOrFail());

        $this->refreshState();

        Flux::toast(variant: 'success', text: __('Plan cancelled. It stays active until the end of the paid month.'));
    }

    public function resume(ResumeClubSubscription $resumeClubSubscription): void
    {
        $this->authorize('update', $this->club);

        $resumed = $resumeClubSubscription->handle($this->latestSubscriptionOrFail());

        $this->refreshState();

        Flux::toast(
            variant: $resumed ? 'success' : 'danger',
            text: $resumed ? __('Plan resumed. Billing continues after the current month.') : __('This plan can no longer be resumed. Choose a plan again instead.'),
        );
    }

    public function retryPayment(ChargeClubSubscription $chargeClubSubscription): void
    {
        $this->authorize('update', $this->club);

        $payment = $chargeClubSubscription->handle($this->latestSubscriptionOrFail(), retry: true);

        $this->refreshState();

        Flux::toast(
            variant: $payment ? 'success' : 'danger',
            text: $payment ? __('A new direct debit has been started. Your plan is reactivated once it is paid.') : __('The payment could not be retried.'),
        );
    }

    /**
     * Superadmins activate plans that are invoiced outside SquadPass, such as Enterprise.
     */
    public function activateManually(string $plan, ActivateManualClubPlan $activateManualClubPlan): void
    {
        $this->authorize('manage-platform');

        try {
            $activateManualClubPlan->handle($this->club, ClubPlan::from($plan), Auth::user());
        } catch (ClubPlanException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->refreshState();

        Flux::toast(variant: 'success', text: __('Plan activated.'));
    }

    /**
     * The club's most recent subscription, whatever its state.
     */
    #[Computed]
    public function subscription(): ?ClubSubscription
    {
        return ClubSubscription::query()
            ->where('club_id', $this->club->id)
            ->latest('id')
            ->first();
    }

    #[Computed]
    public function activeMemberCount(): int
    {
        return $this->club->activeMemberCount();
    }

    /**
     * @return Collection<int, ClubSubscriptionPayment>
     */
    #[Computed]
    public function payments(): Collection
    {
        return ClubSubscriptionPayment::query()
            ->where('club_id', $this->club->id)
            ->with('subscription:id,plan')
            ->latest('id')
            ->limit(12)
            ->get();
    }

    protected function latestSubscriptionOrFail(): ClubSubscription
    {
        return ClubSubscription::query()
            ->where('club_id', $this->club->id)
            ->latest('id')
            ->firstOrFail();
    }

    protected function refreshState(): void
    {
        unset($this->subscription, $this->payments, $this->activeMemberCount);

        $this->club->forgetSubscriptionCache();
    }

    public function render(): View
    {
        return view('livewire.clubs.plan', [
            'plans' => ClubPlan::cases(),
            'statuses' => SubscriptionStatus::class,
        ]);
    }
}
