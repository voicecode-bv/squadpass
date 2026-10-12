<?php

namespace App\Livewire\Portal;

use App\Actions\Payments\CreateStripCardPayment;
use App\Enums\MembershipStatus;
use App\Exceptions\PaymentException;
use App\Models\Club;
use App\Models\StripCardPlan;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Buy strip card')]
class BuyStripCard extends Component
{
    public function buy(int $planId, CreateStripCardPayment $createStripCardPayment): void
    {
        $plan = StripCardPlan::query()
            ->active()
            ->with('club')
            ->findOrFail($planId);

        try {
            $checkoutUrl = $createStripCardPayment->handle(Auth::user(), $plan);
        } catch (PaymentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->redirect($checkoutUrl);
    }

    /**
     * @return Collection<int, StripCardPlan>
     */
    #[Computed]
    public function plans(): Collection
    {
        $clubIds = Auth::user()->memberships()
            ->where('status', MembershipStatus::Active)
            ->pluck('club_id');

        return StripCardPlan::query()
            ->active()
            ->whereIn('club_id', $clubIds)
            ->with('club')
            ->orderBy('club_id')
            ->orderBy('strips')
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
        return $this->plans
            ->pluck('club')
            ->unique('id')
            ->filter(fn (Club $club): bool => $club->acceptsOnlinePayments())
            ->mapWithKeys(fn (Club $club): array => [$club->id => true])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.portal.buy-strip-card');
    }
}
