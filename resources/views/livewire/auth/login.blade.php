<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
        </div>

        @env('local')
            <flux:separator :text="__('Development logins')" />

            <div class="flex flex-col gap-1 text-center text-sm">
                <x-login-link email="owner@example.com" :label="__('Login as club owner')" class="text-blue-600 hover:underline dark:text-blue-400" />
                <x-login-link email="tessa@example.com" :label="__('Login as trainer')" class="text-blue-600 hover:underline dark:text-blue-400" />
                <x-login-link email="lid1@example.com" :label="__('Login as member')" class="text-blue-600 hover:underline dark:text-blue-400" />
                <x-login-link email="superadmin@example.com" :label="__('Login as superadmin')" class="text-blue-600 hover:underline dark:text-blue-400" />
            </div>
        @endenv
    </div>
</x-layouts::auth>
