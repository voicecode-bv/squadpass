<div class="rounded-3xl bg-white dark:bg-zinc-900 p-6 ring-1 ring-zinc-200 dark:ring-white/10 sm:p-8">
    <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Create your club') }}</h2>
    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Your account and club are ready in one step.') }}</p>

    <form wire:submit="signup($event.target.querySelector('[name=cf-turnstile-response]')?.value ?? null)" class="relative mt-6 flex flex-col gap-5">
        <flux:input
            wire:model="club_name"
            :label="__('Club name')"
            type="text"
            required
            autocomplete="organization"
            placeholder="Padel Club Amsterdam"
        />

        <flux:input wire:model="first_name" :label="__('First name')" type="text" required autocomplete="given-name" />

        <div class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-4">
            <flux:input wire:model="name_prefix" :label="__('Name prefix')" type="text" autocomplete="additional-name" :placeholder="__('e.g. van der')" />
            <flux:input wire:model="last_name" :label="__('Last name')" type="text" required autocomplete="family-name" />
        </div>

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

        <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Already have an account?') }}
            <a href="{{ route('login') }}" class="font-medium text-blue-600 dark:text-blue-300 hover:text-zinc-900 dark:hover:text-white">{{ __('Log in') }}</a>
        </p>
    </form>
</div>
