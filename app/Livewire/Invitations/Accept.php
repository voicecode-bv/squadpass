<?php

namespace App\Livewire\Invitations;

use App\Actions\Invitations\AcceptClubInvitation;
use App\Enums\ClubInvitationRole;
use App\Exceptions\ClubInvitationException;
use App\Exceptions\ClubPlanException;
use App\Models\ClubInvitation;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.auth')]
#[Title('Club invitation')]
class Accept extends Component
{
    public ClubInvitation $invitation;

    public function mount(string $token): void
    {
        $this->invitation = ClubInvitation::query()
            ->where('token', $token)
            ->with('club')
            ->firstOrFail();
    }

    public function accept(AcceptClubInvitation $acceptClubInvitation): void
    {
        abort_unless(Auth::check(), 403);

        try {
            $acceptClubInvitation->handle($this->invitation, Auth::user());
        } catch (ClubInvitationException|ClubPlanException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $club = $this->invitation->club->name;

        Flux::toast(variant: 'success', text: $this->invitation->role === ClubInvitationRole::Trainer
            ? __('You are now a trainer at :club.', ['club' => $club])
            : __('You are now a member of :club.', ['club' => $club]));

        $this->redirectRoute('dashboard', navigate: true);
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
        return view('livewire.invitations.accept');
    }

    protected function rememberIntended(): void
    {
        session()->put('url.intended', route('invitations.accept', $this->invitation->token));
    }
}
