<?php

namespace App\Livewire\Portal;

use App\Actions\Payments\CreateStripCardPayment;
use App\Enums\MembershipStatus;
use App\Exceptions\PaymentException;
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

    public function render(): View
    {
        return view('livewire.portal.buy-strip-card');
    }
}
