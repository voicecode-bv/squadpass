<?php

namespace App\Livewire\Portal;

use App\Models\StripCard;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('My strip cards')]
class MyCards extends Component
{
    /**
     * @return Collection<int, StripCard>
     */
    #[Computed]
    public function cards(): Collection
    {
        return StripCard::query()
            ->where('user_id', Auth::id())
            ->with(['club', 'plan'])
            ->latest('purchased_at')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.portal.my-cards');
    }
}
