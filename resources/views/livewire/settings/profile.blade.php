<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        {{-- Avatar --}}
        <div class="mt-6 flex items-center gap-4" x-data="avatarCropper">
            <flux:avatar size="xl" :src="auth()->user()->avatarUrl()" :name="auth()->user()->name" :initials="auth()->user()->initials()" />

            <div class="flex gap-2">
                <flux:button size="sm" icon="photo" x-on:click="$refs.avatarInput.click()">{{ __('Upload avatar') }}</flux:button>
                @if (auth()->user()->avatar_path)
                    <flux:button size="sm" variant="ghost" wire:click="deleteAvatar">{{ __('Remove avatar') }}</flux:button>
                @endif
            </div>

            <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" x-ref="avatarInput" x-on:change="startCropping($event)" />
            <flux:error name="avatar" />

            {{-- Crop dialog --}}
            <div x-cloak x-show="cropping" class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/60 p-4">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-900" x-on:click.outside="cancel()">
                    <flux:heading size="lg">{{ __('Crop your photo') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('Drag and zoom until your photo fits the square.') }}</flux:text>

                    <div class="mt-4 max-h-80 overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                        <img x-ref="cropImage" class="block max-h-80 max-w-full" alt="" />
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <flux:button variant="ghost" x-on:click="cancel()">{{ __('Cancel') }}</flux:button>
                        <flux:button variant="primary" x-on:click="save()" x-bind:disabled="uploading">
                            <span x-show="! uploading">{{ __('Save avatar') }}</span>
                            <span x-cloak x-show="uploading">{{ __('Uploading...') }}</span>
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            @if (auth()->user()->needsNameReview())
                <flux:callout icon="information-circle" variant="secondary">
                    <flux:callout.heading>{{ __('Check your name') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('We now store first name, name prefix and last name separately and split your name automatically. Check that it is right and save.') }}</flux:callout.text>
                </flux:callout>
            @endif

            <div class="grid gap-4 sm:grid-cols-[2fr_1fr_2fr]">
                <flux:input wire:model="first_name" :label="__('First name')" type="text" required autofocus autocomplete="given-name" />
                <flux:input wire:model="name_prefix" :label="__('Name prefix')" type="text" autocomplete="additional-name" :placeholder="__('e.g. van der')" />
                <flux:input wire:model="last_name" :label="__('Last name')" type="text" required autocomplete="family-name" />
            </div>

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:settings.delete-user-form />
        @endif
    </x-settings.layout>
</section>
