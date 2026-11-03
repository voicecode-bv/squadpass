@props([
    'clubs',
    'currentClub' => null,
    'hasMore' => false,
])

{{-- Club picker in the top bar: the current club as the button, the user's staff clubs in the menu. --}}
<flux:dropdown position="bottom" align="start" data-test="club-switcher">
    {{-- On phones only the club photo shows, so the search keeps its room. --}}
    <flux:button variant="ghost" icon:trailing="chevron-up-down" class="shrink-0 sm:max-w-64 xl:max-w-80" :aria-label="$currentClub?->name ?? __('Choose a club')">
        @if ($currentClub)
            <span class="flex min-w-0 items-center gap-2">
                <flux:avatar size="xs" :src="$currentClub->photoUrl()" :name="$currentClub->name" />
                <span class="sp-strong truncate max-sm:hidden">{{ $currentClub->name }}</span>
            </span>
        @else
            <flux:icon.building-storefront variant="mini" class="sm:hidden" />
            <span class="truncate max-sm:hidden">{{ __('Choose a club') }}</span>
        @endif
    </flux:button>

    <flux:menu class="min-w-64">
        @foreach ($clubs as $club)
            <flux:menu.item
                :href="route('clubs.dashboard', $club)"
                wire:navigate
                wire:key="club-switcher-{{ $club->id }}"
            >
                <span class="flex w-full min-w-0 items-center gap-2">
                    <flux:avatar size="xs" :src="$club->photoUrl()" :name="$club->name" />
                    <span class="truncate">{{ $club->name }}</span>
                    @if ($currentClub?->is($club))
                        <flux:icon.check variant="micro" class="ms-auto size-4 shrink-0 text-blue-600" />
                    @endif
                </span>
            </flux:menu.item>
        @endforeach

        @if ($clubs->isNotEmpty())
            <flux:menu.separator />
        @endif

        <flux:menu.item :href="route('clubs.index')" icon="building-storefront" wire:navigate>
            {{ $hasMore ? __('All clubs') : __('My clubs') }}
        </flux:menu.item>
    </flux:menu>
</flux:dropdown>
