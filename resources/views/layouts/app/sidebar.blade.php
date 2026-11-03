<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas">
        @php
            $user = auth()->user();
            $currentClub = request()->route('club');
            $currentClub = $currentClub instanceof \App\Models\Club && $user->can('view', $currentClub) ? $currentClub : null;

            // One query for the switcher: staff see their own clubs, superadmins every club.
            // One row extra tells whether "All clubs" should link to more.
            $switcherClubs = ($user->is_superadmin ? \App\Models\Club::query() : $user->staffClubs())
                ->orderBy('clubs.name')
                ->limit(\App\Models\User::CLUB_SWITCHER_LIMIT + 1)
                ->get(['clubs.id', 'clubs.name', 'clubs.slug', 'clubs.photo_path']);
            $switcherHasMore = $switcherClubs->count() > \App\Models\User::CLUB_SWITCHER_LIMIT;
            $switcherClubs = $switcherClubs->take(\App\Models\User::CLUB_SWITCHER_LIMIT);

            // The open club always appears in the list, also when it falls outside the first page.
            if ($currentClub && ! $switcherClubs->contains($currentClub)) {
                $switcherClubs = $switcherClubs->prepend($currentClub);
            }
            $managesClubs = $user->is_superadmin || $switcherClubs->isNotEmpty();

            // A single club is selected by default and replaces the personal dashboard (see User::soleStaffClub()).
            $soleClub = ! $user->is_superadmin && $switcherClubs->count() === 1 ? $switcherClubs->first() : null;
            $currentClub ??= $soleClub;
            $homeUrl = $soleClub ? route('clubs.dashboard', $soleClub) : route('dashboard');

            // Newest unread tickets; one extra row shows "9+" without counting them all.
            $unreadTicketLimit = 9;
            $unreadTickets = \App\Models\Ticket::query()
                ->unreadFor($user)
                ->with('club:id,name,slug')
                ->orderByDesc('tickets.last_message_at')
                ->limit($unreadTicketLimit + 1)
                ->get(['tickets.id', 'tickets.club_id', 'tickets.user_id', 'tickets.number', 'tickets.subject', 'tickets.last_message_at']);
        @endphp

        <flux:sidebar sticky collapsible="mobile" class="bg-white dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ $homeUrl }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="gap-6">
                {{-- One block, so the nav's group gap does not separate Dashboard and Settings. --}}
                <div class="grid">
                    {{-- Always the top item: the selected club's dashboard, or the personal one when no club is open. --}}
                    <flux:sidebar.item
                        icon="home"
                        :href="$currentClub ? route('clubs.dashboard', $currentClub) : route('dashboard')"
                        :current="request()->routeIs($currentClub ? 'clubs.dashboard' : 'dashboard')"
                        wire:navigate
                    >
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    @if ($currentClub && (
                        $user->can('update', $currentClub)
                        || $user->can('access', [$currentClub, \App\Enums\StaffPermission::Forms])
                        || $user->can('access', [$currentClub, \App\Enums\StaffPermission::Setup])
                        || ($currentClub->hasAddon(\App\Enums\AddonKey::Mollie) && $user->can('access', [$currentClub, \App\Enums\StaffPermission::Billing]))
                    ))
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('clubs.settings', $currentClub)" :current="request()->routeIs('clubs.settings*', 'clubs.staff', 'clubs.forms*', 'clubs.trainers', 'clubs.rooms', 'clubs.plan', 'clubs.addons', 'clubs.moneybird', 'clubs.mollie')" wire:navigate>
                            {{ __('Settings') }}
                        </flux:sidebar.item>
                    @endif
                </div>

                @if ($currentClub)
                    @php
                        $permissions = \App\Enums\StaffPermission::class;
                        $can = fn (\App\Enums\StaffPermission $permission): bool => $user->can('access', [$currentClub, $permission]);
                    @endphp

                    @if ($can($permissions::Members) || $can($permissions::CheckIns) || $can($permissions::Tickets))
                        <flux:sidebar.group :heading="__('Members')" data-sidebar-group="club-members" class="grid">
                            @if ($can($permissions::Members))
                                <flux:sidebar.item icon="users" :href="route('clubs.members', $currentClub)" :current="request()->routeIs('clubs.members*')" wire:navigate>
                                    {{ __('Members') }}
                                </flux:sidebar.item>
                            @endif
                            @if ($can($permissions::CheckIns))
                                <flux:sidebar.item icon="finger-print" :href="route('clubs.check-ins', $currentClub)" :current="request()->routeIs('clubs.check-ins*')" wire:navigate>
                                    {{ __('Check-in desk') }}
                                </flux:sidebar.item>
                            @endif
                            @if ($can($permissions::Tickets))
                                <flux:sidebar.item icon="lifebuoy" :href="route('clubs.tickets', $currentClub)" :current="request()->routeIs('clubs.tickets*')" wire:navigate>
                                    {{ __('Tickets') }}
                                </flux:sidebar.item>
                            @endif
                        </flux:sidebar.group>
                    @endif

                    @if ($can($permissions::Lessons))
                        <flux:sidebar.group :heading="__('Lessons')" data-sidebar-group="club-lessons" class="grid">
                            <flux:sidebar.item icon="calendar" :href="route('clubs.lessons', $currentClub)" :current="request()->routeIs('clubs.lessons*')" wire:navigate>
                                {{ __('Lessons') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="clock" :href="route('clubs.schedule', $currentClub)" :current="request()->routeIs('clubs.schedule', 'clubs.occurrences.*')" wire:navigate>
                                {{ __('Schedule') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @endif

                    @if ($can($permissions::Billing))
                        <flux:sidebar.group :heading="__('Billing')" data-sidebar-group="club-billing" class="grid">
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
                    @endif
                @endif

                @if (auth()->user()->trainerProfiles()->exists())
                    <flux:sidebar.group :heading="__('Trainer')" data-sidebar-group="trainer" class="grid">
                        <flux:sidebar.item icon="academic-cap" :href="route('trainer.lessons')" :current="request()->routeIs('trainer.lessons')" wire:navigate>
                            {{ __('Lessons I teach') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                @if (auth()->user()->memberships()->exists())
                    <flux:sidebar.group :heading="__('My lessons')" data-sidebar-group="lessons" class="grid">
                        <flux:sidebar.item icon="calendar-days" :href="route('portal.schedule')" :current="request()->routeIs('portal.schedule')" wire:navigate>
                            {{ __('Lesson schedule') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-check" :href="route('portal.enrollments')" :current="request()->routeIs('portal.enrollments')" wire:navigate>
                            {{ __('My enrollments') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('My membership')" data-sidebar-group="membership" class="grid">
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

                    <flux:sidebar.group :heading="__('My support')" data-sidebar-group="support" class="grid">
                        <flux:sidebar.item icon="lifebuoy" :href="route('portal.tickets')" :current="request()->routeIs('portal.tickets*')" wire:navigate>
                            {{ __('Support tickets') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                @if ($user->is_superadmin)
                    <flux:sidebar.group :heading="__('Platform')" data-sidebar-group="platform" class="grid">
                        <flux:sidebar.item icon="building-storefront" :href="route('clubs.index')" :current="request()->routeIs('clubs.index')" wire:navigate>
                            {{ __('All clubs') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="puzzle-piece" :href="route('admin.addons')" :current="request()->routeIs('admin.addons')" wire:navigate>
                            {{ __('Add-ons') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="credit-card" :href="route('admin.club-plans')" :current="request()->routeIs('admin.club-plans')" wire:navigate>
                            {{ __('SquadPass plans') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            {{-- No flux:spacer: the nav grows and scrolls, this stays pinned to the bottom. --}}
            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        {{-- No Flux `sticky` prop here: it binds an inline `top: $el.offsetTop + 'px'`,
             which lands below the viewport top when the page is already scrolled
             (for example right after a wire:navigate). Plain sticky top-0 is stable. --}}
        {{-- Three zones: club context on the left, search centred in the free space, account on the right. --}}
        <flux:header class="sticky top-0 z-20 gap-2 bg-white sm:gap-4 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            @if ($managesClubs)
                <x-club-switcher :clubs="$switcherClubs" :current-club="$currentClub" :has-more="$switcherHasMore" />

                <flux:separator vertical class="my-4 max-sm:hidden" />

                {{-- Phones skip the search: the top bar keeps room for the club, notifications and account. --}}
                <flux:spacer class="sm:hidden" />

                <div class="flex min-w-0 flex-1 justify-center max-sm:hidden">
                    <livewire:global-search />
                </div>
            @else
                <flux:spacer />
            @endif

            <x-unread-tickets :tickets="$unreadTickets" :limit="$unreadTicketLimit" />

            <flux:dropdown position="bottom" align="end" class="shrink-0">
                {{-- The name only shows from tablet width; on phones the avatar is enough. --}}
                <flux:profile
                    :avatar="auth()->user()->avatarUrl()"
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                    class="max-w-56 max-md:[&>span]:hidden"
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
                        <flux:menu.item icon="information-circle" x-on:click="Livewire.dispatch('open-product-tour')" class="w-full cursor-pointer" data-test="open-product-tour">
                            {{ __('How SquadPass works') }}
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
        @include('partials.demo-banner')

        <livewire:product-tour />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
