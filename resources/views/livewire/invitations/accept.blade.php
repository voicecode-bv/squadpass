<div class="mx-auto mt-16 flex w-full max-w-md flex-col gap-6 px-4">
    @php $isTrainer = $invitation->role === \App\Enums\ClubInvitationRole::Trainer; @endphp

    <div class="flex flex-col gap-2 text-center">
        <flux:heading size="xl">{{ $isTrainer ? __('Trainer invitation') : __('Membership invitation') }}</flux:heading>
        <flux:text>
            {{ $isTrainer
                ? __('You have been invited to become a trainer at :club.', ['club' => $invitation->club->name])
                : __('You have been invited to become a member of :club.', ['club' => $invitation->club->name]) }}
        </flux:text>
    </div>

    @if ($invitation->isAccepted())
        <flux:callout icon="check-circle" variant="success">
            <flux:callout.heading>{{ __('Invitation already accepted') }}</flux:callout.heading>
            <flux:callout.text>{{ __('This invitation has already been used.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($invitation->isExpired())
        <flux:callout icon="clock" variant="warning">
            <flux:callout.heading>{{ __('Invitation expired') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Ask the club to send you a new invitation.') }}</flux:callout.text>
        </flux:callout>
    @elseif (auth()->check())
        <flux:callout :icon="$isTrainer ? 'academic-cap' : 'users'">
            <flux:callout.text>
                {{ $isTrainer
                    ? __('You are logged in as :name. Accept to become a trainer at :club.', ['name' => auth()->user()->name, 'club' => $invitation->club->name])
                    : __('You are logged in as :name. Accept to become a member of :club.', ['name' => auth()->user()->name, 'club' => $invitation->club->name]) }}
            </flux:callout.text>
        </flux:callout>

        <flux:button variant="primary" wire:click="accept" icon="check">
            {{ __('Accept invitation') }}
        </flux:button>
    @else
        <flux:text class="text-center">
            {{ __('Log in with your existing account, or create a new account to accept the invitation.') }}
        </flux:text>

        <div class="flex flex-col gap-3">
            <flux:button variant="primary" wire:click="goToLogin">{{ __('Log in') }}</flux:button>
            <flux:button wire:click="goToRegister">{{ __('Create an account') }}</flux:button>
        </div>
    @endif
</div>
