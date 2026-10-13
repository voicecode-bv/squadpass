<div class="rounded-3xl bg-zinc-900 p-6 ring-1 ring-white/10 sm:p-8">
    <h2 class="text-2xl font-semibold text-white">{{ __('Create your club') }}</h2>
    <p class="mt-1 text-sm text-zinc-400">{{ __('Your account and club are ready in one step.') }}</p>

    <form wire:submit="signup($event.target.querySelector('[name=cf-turnstile-response]')?.value ?? null)" class="relative mt-6 flex flex-col gap-5">
        <flux:input
            wire:model="club_name"
            :label="__('Club name')"
            type="text"
            required
            autocomplete="organization"
            placeholder="Padel Club Amsterdam"
        />

        <flux:input
            wire:model="name"
            :label="__('Your name')"
            type="text"
            required
            autocomplete="name"
            :placeholder="__('Full name')"
        />

        <flux:input
            wire:model="email"
            :label="__('Email address')"
            type="email"
            required
            autocomplete="email"
            placeholder="email@example.com"
        />

        <flux:input
            wire:model="password"
            :label="__('Password')"
            type="password"
            required
            autocomplete="new-password"
            :placeholder="__('Password')"
            passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            viewable
        />

        <x-spam-protection livewire />

        <flux:button type="submit" variant="primary" class="w-full" data-test="club-signup-button">
            {{ __('Create my club') }}
        </flux:button>

        <p class="text-center text-sm text-zinc-400">
            {{ __('Already have an account?') }}
            <a href="{{ route('login') }}" class="font-medium text-blue-300 hover:text-white">{{ __('Log in') }}</a>
        </p>
    </form>
</div>
