<?php

namespace App\Livewire\Clubs;

use App\Exceptions\ClubPlanException;
use App\Models\Club;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.auth')]
#[Title('Become a member')]
class Join extends Component
{
    public Club $club;

    public function mount(Club $club): void
    {
        $this->club = $club;
    }

    public function join(): void
    {
        abort_unless(Auth::check(), 403);
        abort_unless($this->club->self_signup, 403);

        // Staff run the club; they do not sign themselves up as one of its members.
        abort_if(Auth::user()->isStaffOf($this->club), 403);

        if (Auth::user()->isMemberOf($this->club)) {
            $this->redirectRoute('portal.schedule', navigate: true);

            return;
        }

        try {
            $membership = $this->club->addMember(Auth::user());
        } catch (ClubPlanException) {
            // The reason (plan or member limit) is the club's business, not the visitor's.
            Flux::toast(variant: 'danger', text: __('This club cannot accept new members right now. Please contact the club.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('Welcome! You are now a member of :club (#:number).', [
            'club' => $this->club->name,
            'number' => $membership->membership_number,
        ]));

        $this->redirectRoute('portal.schedule', navigate: true);
    }

    public function goToLogin(): void
    {
        $this->rememberIntended();

        $this->redirectRoute('login');
    }

    public function goToRegister(): void
    {
        $this->rememberIntended();

        $this->redirectRoute('register');
    }

    public function render(): View
    {
        return view('livewire.clubs.join');
    }

    protected function rememberIntended(): void
    {
        session()->put('url.intended', route('clubs.join', $this->club));
    }
}
