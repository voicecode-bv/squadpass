<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('My clubs')" :subtitle="auth()->user()->is_superadmin ? __('All clubs on the platform.') : __('Clubs you manage as staff.')">
        <x-slot:actions>
            @can('create', \App\Models\Club::class)
                <flux:modal.trigger name="create-club">
                    <flux:button variant="primary" icon="plus">{{ __('New club') }}</flux:button>
                </flux:modal.trigger>
            @endcan
        </x-slot:actions>
    </x-portal.hero>

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
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->clubs as $clubIndex => $club)
                <x-portal.card :delay="min(600, $clubIndex * 70)" wire:key="club-{{ $club->id }}">
                    <a href="{{ $club->isOnboarded() ? route('clubs.dashboard', $club) : route('clubs.onboarding', $club) }}" wire:navigate class="block">
                        @if ($club->photoUrl())
                            <img src="{{ $club->photoUrl() }}" alt="{{ $club->name }}"
                                 class="h-72 w-full object-cover transition duration-300 group-hover:scale-[1.03]" loading="lazy" />
                        @else
                            <div class="flex h-72 w-full items-center justify-center bg-blue-50 text-3xl font-semibold text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                                {{ str($club->name)->substr(0, 1)->upper() }}
                            </div>
                        @endif

                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <flux:heading class="sp-card-title">{{ $club->name }}</flux:heading>
                                @unless ($club->pivot?->role)
                                    <flux:badge size="sm" color="purple">{{ __('Superadmin') }}</flux:badge>
                                @endunless
                            </div>
                            <flux:text class="sp-card-subtitle mt-1">{{ $club->slug }}</flux:text>
                            @unless ($club->isOnboarded())
                                <flux:badge size="sm" color="yellow" class="mt-2">{{ __('Finish setup') }}</flux:badge>
                            @endunless
                        </div>
                    </a>
                </x-portal.card>
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
