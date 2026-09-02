<?php

namespace App\Livewire\Portal;

use App\Models\CheckIn;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('My visits')]
class History extends Component
{
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, CheckIn>
     */
    #[Computed]
    public function checkIns(): LengthAwarePaginator
    {
        return CheckIn::query()
            ->where('user_id', Auth::id())
            ->with(['club', 'occurrence.lesson'])
            ->latest('checked_in_at')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.portal.history');
    }
}
