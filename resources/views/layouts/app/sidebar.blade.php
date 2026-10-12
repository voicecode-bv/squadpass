<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas">
        <flux:sidebar sticky collapsible="mobile" class="bg-white dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="gap-6">
                <flux:sidebar.group :heading="__('Platform')" expandable data-sidebar-group="platform" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @if (auth()->user()->is_superadmin || auth()->user()->staffClubs()->exists())
                        <flux:sidebar.item icon="building-storefront" :href="route('clubs.index')" :current="request()->routeIs('clubs.index')" wire:navigate>
                            {{ __('My clubs') }}
                        </flux:sidebar.item>
                    @endif
                    @if (auth()->user()->is_superadmin)
                        <flux:sidebar.item icon="puzzle-piece" :href="route('admin.addons')" :current="request()->routeIs('admin.addons')" wire:navigate>
                            {{ __('Add-ons') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>

                @if (auth()->user()->trainerProfiles()->exists())
                    <flux:sidebar.group :heading="__('Trainer')" expandable data-sidebar-group="trainer" class="grid">
                        <flux:sidebar.item icon="academic-cap" :href="route('trainer.lessons')" :current="request()->routeIs('trainer.lessons')" wire:navigate>
                            {{ __('My lessons') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                @if (auth()->user()->memberships()->exists())
                    <flux:sidebar.group :heading="__('Lessons')" expandable data-sidebar-group="lessons" class="grid">
                        <flux:sidebar.item icon="calendar-days" :href="route('portal.schedule')" :current="request()->routeIs('portal.schedule')" wire:navigate>
                            {{ __('Lesson schedule') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-check" :href="route('portal.enrollments')" :current="request()->routeIs('portal.enrollments')" wire:navigate>
                            {{ __('My enrollments') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('My membership')" expandable data-sidebar-group="membership" class="grid">
                        <flux:sidebar.item icon="ticket" :href="route('portal.cards')" :current="request()->routeIs('portal.cards*')" wire:navigate>
                            {{ __('My strip cards') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="credit-card" :href="route('portal.subscriptions')" :current="request()->routeIs('portal.subscriptions')" wire:navigate>
                            {{ __('My subscriptions') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="finger-print" :href="route('portal.history')" :current="request()->routeIs('portal.history')" wire:navigate>
                            {{ __('My visits') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('portal.forms')" :current="request()->routeIs('portal.forms*')" wire:navigate>
                            {{ __('My forms') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Support')" expandable data-sidebar-group="support" class="grid">
                        <flux:sidebar.item icon="lifebuoy" :href="route('portal.tickets')" :current="request()->routeIs('portal.tickets*')" wire:navigate>
                            {{ __('Support tickets') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                @if (($currentClub = request()->route('club')) instanceof \App\Models\Club && auth()->user()->can('view', $currentClub))
                    <flux:sidebar.group :heading="$currentClub->name" expandable data-sidebar-group="club" class="grid">
                        <flux:sidebar.item icon="chart-bar" :href="route('clubs.dashboard', $currentClub)" :current="request()->routeIs('clubs.dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>
                        @can('update', $currentClub)
                            <flux:sidebar.item icon="cog-6-tooth" :href="route('clubs.settings', $currentClub)" :current="request()->routeIs('clubs.settings')" wire:navigate>
                                {{ __('Settings') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="puzzle-piece" :href="route('clubs.addons', $currentClub)" :current="request()->routeIs('clubs.addons')" wire:navigate>
                                {{ __('Add-ons') }}
                            </flux:sidebar.item>
                            @if ($currentClub->hasAddon(\App\Enums\AddonKey::Moneybird))
                                <flux:sidebar.item icon="document-text" :href="route('clubs.moneybird', $currentClub)" :current="request()->routeIs('clubs.moneybird')" wire:navigate>
                                    {{ __('Moneybird') }}
                                </flux:sidebar.item>
                            @endif
                        @endcan
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Members')" expandable data-sidebar-group="club-members" class="grid">
                        <flux:sidebar.item icon="users" :href="route('clubs.members', $currentClub)" :current="request()->routeIs('clubs.members')" wire:navigate>
                            {{ __('Members') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="academic-cap" :href="route('clubs.trainers', $currentClub)" :current="request()->routeIs('clubs.trainers')" wire:navigate>
                            {{ __('Trainers') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="finger-print" :href="route('clubs.check-ins', $currentClub)" :current="request()->routeIs('clubs.check-ins')" wire:navigate>
                            {{ __('Check-in desk') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('clubs.forms', $currentClub)" :current="request()->routeIs('clubs.forms*')" wire:navigate>
                            {{ __('Forms') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Lessons')" expandable data-sidebar-group="club-lessons" class="grid">
                        <flux:sidebar.item icon="calendar" :href="route('clubs.lessons', $currentClub)" :current="request()->routeIs('clubs.lessons*')" wire:navigate>
                            {{ __('Lessons') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clock" :href="route('clubs.schedule', $currentClub)" :current="request()->routeIs('clubs.schedule')" wire:navigate>
                            {{ __('Schedule') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="map-pin" :href="route('clubs.rooms', $currentClub)" :current="request()->routeIs('clubs.rooms')" wire:navigate>
                            {{ __('Rooms') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Billing')" expandable data-sidebar-group="club-billing" class="grid">
                        <flux:sidebar.item icon="ticket" :href="route('clubs.strip-cards', $currentClub)" :current="request()->routeIs('clubs.strip-cards')" wire:navigate>
                            {{ __('Strip cards') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="credit-card" :href="route('clubs.subscription-plans', $currentClub)" :current="request()->routeIs('clubs.subscription-plans')" wire:navigate>
                            {{ __('Subscription plans') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="banknotes" :href="route('clubs.subscriptions', $currentClub)" :current="request()->routeIs('clubs.subscriptions')" wire:navigate>
                            {{ __('Subscriptions') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="receipt-percent" :href="route('clubs.invoices', $currentClub)" :current="request()->routeIs('clubs.invoices')" wire:navigate>
                            {{ __('Invoices') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Support')" expandable data-sidebar-group="club-support" class="grid">
                        <flux:sidebar.item icon="lifebuoy" :href="route('clubs.tickets', $currentClub)" :current="request()->routeIs('clubs.tickets*')" wire:navigate>
                            {{ __('Tickets') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            {{-- No flux:spacer: the nav grows and scrolls, this stays pinned to the bottom. --}}
            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />

            {{-- Runs before @fluxScripts upgrades <ui-disclosure>, so a collapsed group never flashes open. --}}
            <script>
                document.querySelectorAll('[data-sidebar-group]').forEach((group) => {
                    try {
                        const stored = localStorage.getItem('sidebar-group:' + group.dataset.sidebarGroup);

                        if (stored === 'closed') {
                            group.removeAttribute('open');
                        } else if (stored === 'open') {
                            group.setAttribute('open', '');
                        }
                    } catch (error) {
                        // Private mode or blocked storage: keep the server-rendered state.
                    }
                });
            </script>
        </flux:sidebar>

        {{-- No Flux `sticky` prop here: it binds an inline `top: $el.offsetTop + 'px'`,
             which lands below the viewport top when the page is already scrolled
             (for example right after a wire:navigate). Plain sticky top-0 is stable. --}}
        <flux:header class="sticky top-0 z-20 bg-white dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            @if (auth()->user()->is_superadmin || auth()->user()->staffClubs()->exists())
                <livewire:global-search />
            @endif

            <flux:spacer />

            <flux:dropdown position="bottom" align="end">
                <flux:profile
                    :avatar="auth()->user()->avatarUrl()"
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :src="auth()->user()->avatarUrl()"
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @include('partials.impersonation-banner')

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
