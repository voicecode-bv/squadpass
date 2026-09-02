<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('My clubs') }}</flux:heading>
            <flux:text class="mt-1">{{ auth()->user()->is_superadmin ? __('All clubs on the platform.') : __('Clubs you manage as staff.') }}</flux:text>
        </div>

        @can('create', \App\Models\Club::class)
            <flux:modal.trigger name="create-club">
                <flux:button variant="primary" icon="plus">{{ __('New club') }}</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    @if ($this->clubs->isEmpty())
        <flux:callout icon="building-storefront">
            <flux:callout.heading>{{ __('No clubs yet') }}</flux:callout.heading>
            <flux:callout.text>
                @can('create', \App\Models\Club::class)
                    {{ __('Create your first club to start managing members, lessons and subscriptions.') }}
                @else
                    {{ __('You are not a staff member of any club yet.') }}
                @endcan
            </flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($this->clubs as $club)
                <a href="{{ $club->isOnboarded() ? route('clubs.dashboard', $club) : route('clubs.onboarding', $club) }}" wire:navigate
                   class="rounded-xl bg-white p-4 shadow-sm transition hover:shadow-md dark:bg-zinc-900 dark:hover:bg-zinc-800">
                    <div class="flex items-center justify-between">
                        <flux:heading>{{ $club->name }}</flux:heading>
                        @if ($club->pivot?->role)
                            <flux:badge size="sm">{{ \App\Enums\StaffRole::from($club->pivot->role)->label() }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="purple">{{ __('Superadmin') }}</flux:badge>
                        @endif
                    </div>
                    <flux:text class="mt-1 text-sm">{{ $club->slug }}</flux:text>
                    @unless ($club->isOnboarded())
                        <flux:badge size="sm" color="yellow" class="mt-2">{{ __('Finish setup') }}</flux:badge>
                    @endunless
                </a>
            @endforeach
        </div>
    @endif

    <flux:modal name="create-club" class="md:w-96">
        <form wire:submit="createClub" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New club') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ auth()->user()->is_superadmin ? __('Optionally assign an owner by email; otherwise you become the owner.') : __('You will become the owner of this club.') }}
                </flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Club name')" required autofocus />

            @if (auth()->user()->is_superadmin)
                <flux:input wire:model="owner_email" :label="__('Owner email (optional)')" type="email" />
            @endif

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Create club') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
