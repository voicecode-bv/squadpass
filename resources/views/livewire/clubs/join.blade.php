<div class="mx-auto mt-16 flex w-full max-w-md flex-col gap-6 px-4">
    <div class="flex flex-col gap-2 text-center">
        <flux:heading size="xl">{{ $club->name }}</flux:heading>
        <flux:text>{{ __('Become a member of :club and book lessons, buy strip cards or take out a subscription.', ['club' => $club->name]) }}</flux:text>
    </div>

    @unless ($club->self_signup)
        <flux:callout icon="lock-closed" variant="warning">
            <flux:callout.heading>{{ __('Registration closed') }}</flux:callout.heading>
            <flux:callout.text>{{ __('This club does not accept online registrations at the moment. Contact the club to become a member.') }}</flux:callout.text>
        </flux:callout>
    @elseif (auth()->check())
        @if (auth()->user()->isMemberOf($club))
            <flux:callout icon="check-circle" variant="success">
                <flux:callout.text>{{ __('You are already a member of this club.') }}</flux:callout.text>
            </flux:callout>

            <flux:button variant="primary" :href="route('portal.schedule')" wire:navigate>{{ __('Go to the lesson schedule') }}</flux:button>
        @else
            <flux:callout icon="users">
                <flux:callout.text>
                    {{ __('You are logged in as :name. Confirm to become a member of :club.', ['name' => auth()->user()->name, 'club' => $club->name]) }}
                </flux:callout.text>
            </flux:callout>

            <flux:button variant="primary" wire:click="join" icon="check">{{ __('Become a member') }}</flux:button>
        @endif
    @else
        <flux:text class="text-center">
            {{ __('Log in with your existing account, or create a new account to become a member.') }}
        </flux:text>

        <div class="flex flex-col gap-3">
            <flux:button variant="primary" wire:click="goToLogin">{{ __('Log in') }}</flux:button>
            <flux:button wire:click="goToRegister">{{ __('Create an account') }}</flux:button>
        </div>
    @endunless
</div>
