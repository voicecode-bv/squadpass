@props(['addonPrices'])

@php
    $lowestAddonPrice = $addonPrices === [] ? null : min($addonPrices);
@endphp

<section id="pricing" class="scroll-mt-8 py-16 lg:py-24">
    <div class="mx-auto max-w-2xl text-center">
        <span class="inline-flex items-center gap-2 rounded-full bg-blue-500/10 px-4 py-1.5 text-sm font-medium text-blue-300 ring-1 ring-blue-400/30">
            <flux:icon.banknotes variant="micro" class="size-4" />
            {{ __('Pricing') }}
        </span>
        <h2 class="mt-6 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ __('One price per month, based on your club size') }}</h2>
        <p class="mt-4 text-lg text-zinc-400">{{ __('Every plan includes all features for your whole club. You only choose which add-ons you switch on.') }}</p>
    </div>

    <div class="mt-14 grid items-stretch gap-6 lg:grid-cols-3">
        @foreach (collect(\App\Enums\ClubPlan::cases())->map(fn (\App\Enums\ClubPlan $clubPlan) => [
            'title' => $clubPlan->label(),
            'text' => $clubPlan->description(),
            'price_cents' => $clubPlan->priceCents(),
            'featured' => $clubPlan === \App\Enums\ClubPlan::Starter,
        ]) as $plan)
            <div class="relative flex h-full flex-col overflow-hidden rounded-3xl p-8 {{ $plan['featured'] ? 'bg-linear-to-br from-blue-600/20 to-indigo-700/10 ring-1 ring-blue-400/40' : 'bg-white/5 ring-1 ring-white/10' }}">
                @if ($plan['featured'])
                    <div class="pointer-events-none absolute -top-20 -right-20 size-56 rounded-full bg-blue-500/20 blur-3xl" aria-hidden="true"></div>
                @endif

                <h3 class="relative text-lg font-semibold text-white">{{ $plan['title'] }}</h3>
                <p class="relative mt-2 text-sm leading-relaxed text-zinc-400">{{ $plan['text'] }}</p>

                <p class="relative mt-8 flex flex-wrap items-baseline gap-x-2">
                    @if ($plan['price_cents'] !== null)
                        <span class="text-4xl font-bold tracking-tight text-white">{{ \App\Models\Addon::formatCents($plan['price_cents']) }}</span>
                        <span class="text-sm text-zinc-400">/ {{ __('month') }}</span>
                    @else
                        <span class="text-4xl font-bold tracking-tight text-white">{{ __('On request') }}</span>
                    @endif
                </p>

                @if ($plan['price_cents'] !== null)
                    <a href="#signup" class="relative mt-8 rounded-xl px-6 py-3 text-center text-base font-semibold transition {{ $plan['featured'] ? 'bg-linear-to-r from-blue-500 to-indigo-600 text-white shadow-lg shadow-blue-500/40 hover:from-blue-400 hover:to-indigo-500' : 'text-zinc-300 ring-1 ring-white/15 hover:bg-white/5 hover:text-white' }}">
                        {{ __('Get started') }}
                    </a>
                @else
                    <a href="mailto:{{ config('mail.from.address') }}" class="relative mt-8 rounded-xl px-6 py-3 text-center text-base font-semibold text-zinc-300 ring-1 ring-white/15 transition hover:bg-white/5 hover:text-white">
                        {{ __('Contact us') }}
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-10 rounded-3xl bg-white/5 p-8 ring-1 ring-white/10">
        <h3 class="text-lg font-semibold text-white">{{ __('Included in every plan') }}</h3>
        <ul class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                __('Unlimited trainers and staff'),
                __('Lessons, schedules and enrollments'),
                __('Strip cards and subscriptions'),
                __('Check-in desk for the front door'),
                __('Member portal on every device'),
                __('Support by ticket included'),
            ] as $included)
                <li class="flex items-start gap-3 text-zinc-300">
                    <flux:icon.check-circle variant="micro" class="mt-0.5 size-5 shrink-0 text-blue-300" />
                    <span>{{ $included }}</span>
                </li>
            @endforeach
        </ul>

        <p class="mt-6 border-t border-white/10 pt-6 text-sm text-zinc-400">
            @if ($lowestAddonPrice !== null)
                {{ __('Add-ons such as online payments, invoicing and your own mobile app are optional and billed separately, from :price per month.', ['price' => \App\Models\Addon::formatCents($lowestAddonPrice)]) }}
            @else
                {{ __('Add-ons such as online payments, invoicing and your own mobile app are optional and billed separately.') }}
            @endif
            <a href="#add-ons" class="ml-1 inline-flex items-center gap-1.5 font-semibold text-blue-300 transition hover:text-white">
                {{ __('Compare add-ons') }}
                <flux:icon.arrow-right variant="micro" class="size-4" />
            </a>
        </p>
    </div>
</section>
