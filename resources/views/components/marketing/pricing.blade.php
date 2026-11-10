@props(['addonPrices'])

@php
    $lowestAddonPrice = $addonPrices === [] ? null : min($addonPrices);
    $launchOfferAvailable = \App\Models\LaunchOffer::isAvailable();
    $launchOfferPercent = \App\Models\LaunchOffer::discountPercent();
    $launchOfferSpots = \App\Models\LaunchOffer::spots();
    $launchOfferSpotsLeft = \App\Models\LaunchOffer::spotsLeft();
@endphp

<section id="pricing" class="scroll-mt-8 py-16 lg:py-24">
    <div class="mx-auto max-w-2xl text-center">
        <span class="inline-flex items-center gap-2 rounded-full bg-blue-500/10 px-4 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-300 ring-1 ring-blue-400/30">
            <flux:icon.banknotes variant="micro" class="size-4" />
            {{ __('Pricing') }}
        </span>
        <h2 class="mt-6 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-4xl">{{ __('One price per month, based on your club size') }}</h2>
        <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">{{ __('Every plan includes all features for your whole club. You only choose which add-ons you switch on.') }}</p>
    </div>

    @if ($launchOfferAvailable)
        <div id="opening-offer" class="mx-auto mt-12 max-w-3xl scroll-mt-8 rounded-3xl bg-linear-to-r from-amber-400/15 via-orange-400/10 to-amber-400/15 p-6 ring-1 ring-amber-300/40 sm:p-8" data-test="launch-offer">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <span class="inline-flex items-center gap-2 text-sm font-semibold tracking-wide text-amber-700 dark:text-amber-200 uppercase">
                        <flux:icon.sparkles variant="micro" class="size-4" />
                        {{ __('Opening offer') }}
                    </span>
                    <p class="mt-2 text-xl font-semibold text-zinc-900 dark:text-white sm:text-2xl">{{ __(':percent% off for good for the first :spots clubs', ['percent' => $launchOfferPercent, 'spots' => $launchOfferSpots]) }}</p>
                    <p class="mt-2 text-zinc-700 dark:text-zinc-300">{{ __('Choose a plan while spots are left and you keep the opening price for as long as you stay a customer, also when you switch plans.') }}</p>
                </div>
                <div class="shrink-0 text-center sm:text-end">
                    <p class="text-4xl font-bold text-zinc-900 dark:text-white tabular-nums">{{ $launchOfferSpotsLeft }}</p>
                    <p class="text-sm text-amber-700 dark:text-amber-200">{{ trans_choice('spot left|spots left', $launchOfferSpotsLeft) }}</p>
                </div>
            </div>
            <div class="mt-6 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/10" aria-hidden="true">
                <div class="h-full rounded-full bg-linear-to-r from-amber-300 to-orange-400" style="width: {{ $launchOfferSpots > 0 ? min(100, (int) round(($launchOfferSpots - $launchOfferSpotsLeft) / $launchOfferSpots * 100)) : 0 }}%"></div>
            </div>
        </div>
    @endif

    <div class="mt-14 grid items-stretch gap-6 lg:grid-cols-3">
        @foreach (collect(\App\Enums\ClubPlan::cases())->map(fn (\App\Enums\ClubPlan $clubPlan) => [
            'title' => $clubPlan->label(),
            'text' => $clubPlan->description(),
            'price_cents' => $clubPlan->priceCents(),
            'featured' => $clubPlan === \App\Enums\ClubPlan::Starter,
        ]) as $plan)
            <div class="relative flex h-full flex-col overflow-hidden rounded-3xl p-8 {{ $plan['featured'] ? 'bg-linear-to-br from-blue-600/20 to-indigo-700/10 ring-1 ring-blue-400/40' : 'bg-white dark:bg-white/5 ring-1 ring-zinc-200 dark:ring-white/10' }}">
                @if ($plan['featured'])
                    <div class="pointer-events-none absolute -top-20 -right-20 size-56 rounded-full bg-blue-500/20 blur-3xl" aria-hidden="true"></div>
                @endif

                <h3 class="relative text-lg font-semibold text-zinc-900 dark:text-white">{{ $plan['title'] }}</h3>
                <p class="relative mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $plan['text'] }}</p>

                <p class="relative mt-8 flex flex-wrap items-baseline gap-x-2">
                    @if ($plan['price_cents'] !== null && $launchOfferAvailable)
                        <span class="text-4xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ \App\Models\Addon::formatCents(\App\Models\LaunchOffer::applyDiscount($plan['price_cents'], $launchOfferPercent)) }}</span>
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">/ {{ __('month') }}</span>
                        <span class="w-full text-sm text-zinc-500">
                            <span class="line-through">{{ \App\Models\Addon::formatCents($plan['price_cents']) }}</span>
                            <span class="ml-1 font-medium text-amber-700 dark:text-amber-200">{{ __('Opening price: :percent% off for good', ['percent' => $launchOfferPercent]) }}</span>
                        </span>
                    @elseif ($plan['price_cents'] !== null)
                        <span class="text-4xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ \App\Models\Addon::formatCents($plan['price_cents']) }}</span>
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">/ {{ __('month') }}</span>
                    @else
                        <span class="text-4xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('On request') }}</span>
                    @endif
                </p>

                @if ($plan['price_cents'] !== null)
                    <a href="#signup" class="relative mt-8 rounded-xl px-6 py-3 text-center text-base font-semibold transition {{ $plan['featured'] ? 'bg-linear-to-r from-blue-500 to-indigo-600 text-white shadow-lg shadow-blue-500/40 hover:from-blue-400 hover:to-indigo-500' : 'text-zinc-700 dark:text-zinc-300 ring-1 ring-zinc-300 dark:ring-white/15 hover:bg-zinc-100 dark:hover:bg-white/5 hover:text-zinc-900 dark:hover:text-white' }}">
                        {{ __('Get started') }}
                    </a>
                @else
                    <a href="mailto:{{ config('mail.from.address') }}" class="relative mt-8 rounded-xl px-6 py-3 text-center text-base font-semibold text-zinc-700 dark:text-zinc-300 ring-1 ring-zinc-300 dark:ring-white/15 transition hover:bg-zinc-100 dark:hover:bg-white/5 hover:text-zinc-900 dark:hover:text-white">
                        {{ __('Contact us') }}
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-10 rounded-3xl bg-white dark:bg-white/5 p-8 ring-1 ring-zinc-200 dark:ring-white/10">
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Included in every plan') }}</h3>
        <ul class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                __('Unlimited trainers and staff'),
                __('Lessons, schedules and enrollments'),
                __('Strip cards and subscriptions'),
                __('Check-in desk for the front door'),
                __('Member portal on every device'),
                __('Support by ticket included'),
            ] as $included)
                <li class="flex items-start gap-3 text-zinc-700 dark:text-zinc-300">
                    <flux:icon.check-circle variant="micro" class="mt-0.5 size-5 shrink-0 text-blue-600 dark:text-blue-300" />
                    <span>{{ $included }}</span>
                </li>
            @endforeach
        </ul>

        <p class="mt-6 border-t border-zinc-200 dark:border-white/10 pt-6 text-sm text-zinc-600 dark:text-zinc-400">
            @if ($lowestAddonPrice !== null)
                {{ __('Add-ons such as online payments, invoicing and your own mobile app are optional and billed separately, from :price per month.', ['price' => \App\Models\Addon::formatCents($lowestAddonPrice)]) }}
            @else
                {{ __('Add-ons such as online payments, invoicing and your own mobile app are optional and billed separately.') }}
            @endif
            <a href="#add-ons" class="ml-1 inline-flex items-center gap-1.5 font-semibold text-blue-600 dark:text-blue-300 transition hover:text-zinc-900 dark:hover:text-white">
                {{ __('Compare add-ons') }}
                <flux:icon.arrow-right variant="micro" class="size-4" />
            </a>
        </p>
    </div>
</section>
