{{--
    White label pitch: the club's own logo (light and dark) and brand colour in
    the app, member portal, public pages and emails. The mock-up on the right is
    painted with a sample palette from BrandPalette, so it shows exactly what a
    club in that colour gets.
--}}
@php($sample = \App\Services\BrandPalette::fromHex('#e11d48'))

<section id="white-label" class="scroll-mt-8 py-16 lg:py-24">
    <div class="grid items-center gap-14 lg:grid-cols-2">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full bg-blue-500/10 px-4 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-300 ring-1 ring-blue-400/30">
                <flux:icon.sparkles variant="micro" class="size-4" />
                {{ __('White label') }}
            </span>
            <h2 class="mt-6 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-4xl">{{ __('Your club, your brand') }}</h2>
            <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">{{ __('SquadPass works under your own name. Your members see your logo and your colours everywhere, from the member portal to the emails in their inbox.') }}</p>

            <ul class="mt-8 flex flex-col gap-4">
                @foreach ([
                    __('Your own logo above the navigation, with a separate version for dark mode'),
                    __('Your brand colour on buttons, menus and charts throughout the app and member portal'),
                    __('Sign-up and trial lesson pages in your style'),
                    __('Emails to your members with your logo and colour'),
                    __('Optionally your own app in the App Store and Google Play'),
                ] as $point)
                    <li class="flex gap-3 text-zinc-700 dark:text-zinc-300">
                        <flux:icon.check-circle variant="mini" class="mt-0.5 size-5 shrink-0 text-blue-600 dark:text-blue-400" />
                        <span>{{ $point }}</span>
                    </li>
                @endforeach
            </ul>

            <p class="mt-8 text-sm text-zinc-500">{{ __('Included in every plan. Set it up yourself in your club settings in a few minutes.') }}</p>

            <a href="{{ route('features.show', \App\Enums\MarketingFeature::WhiteLabel) }}" class="mt-4 inline-flex items-center gap-1.5 font-semibold text-blue-600 dark:text-blue-300 transition hover:text-zinc-900 dark:hover:text-white">
                {{ __('Read more about white label') }}
                <flux:icon.arrow-right variant="micro" class="size-4" />
            </a>
        </div>

        {{-- Mock-up of a club in its own colour; decorative only. --}}
        <div class="relative" aria-hidden="true">
            <div class="pointer-events-none absolute -inset-6 rounded-[2.5rem] blur-3xl" style="background-color: {{ $sample[500] }}; opacity: 0.15"></div>

            <div class="relative overflow-hidden rounded-3xl bg-white shadow-2xl ring-1 ring-white/10">
                <div class="flex items-center justify-between border-b border-zinc-100 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-xl text-sm font-bold text-white" style="background-color: {{ $sample[600] }}">FC</span>
                        <div class="leading-tight">
                            <p class="text-sm font-semibold text-zinc-900">{{ __('Fit Club Centrum') }}</p>
                            <p class="text-[10px] text-zinc-400">{{ __('Powered by :app', ['app' => config('app.name')]) }}</p>
                        </div>
                    </div>
                    <span class="size-8 rounded-full bg-zinc-100"></span>
                </div>

                <div class="flex flex-col gap-4 p-6">
                    <div class="rounded-2xl p-5 text-white" style="background-color: {{ $sample[600] }}">
                        <p class="text-[10px] font-semibold tracking-widest uppercase" style="color: {{ $sample[100] }}">{{ __('Lessons this week') }}</p>
                        <p class="mt-1 text-3xl font-semibold">3</p>
                    </div>

                    @foreach ([['Bootcamp', '18:30'], ['Yoga flow', '20:00']] as [$lesson, $time])
                        <div class="flex items-center justify-between rounded-2xl bg-zinc-50 px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-zinc-900">{{ $lesson }}</p>
                                <p class="text-xs text-zinc-400">{{ __('Today') }} · {{ $time }}</p>
                            </div>
                            <span class="rounded-lg px-3 py-1.5 text-xs font-semibold text-white" style="background-color: {{ $sample[600] }}">{{ __('Book') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- The same brand in an email. --}}
            <div class="relative -mt-8 ml-auto w-64 rounded-2xl bg-white p-4 shadow-xl ring-1 ring-zinc-200 max-sm:mx-auto sm:-mr-4">
                <div class="flex items-center gap-2">
                    <flux:icon.envelope variant="mini" class="size-4 text-zinc-400" />
                    <p class="text-xs font-semibold text-zinc-900">{{ __('Bootcamp starts soon') }}</p>
                </div>
                <div class="mt-3 h-2 w-3/4 rounded-full bg-zinc-100"></div>
                <div class="mt-2 h-2 w-1/2 rounded-full bg-zinc-100"></div>
                <span class="mt-4 inline-block rounded-lg px-3 py-1.5 text-xs font-semibold text-white" style="background-color: {{ $sample[600] }}">{{ __('View my lessons') }}</span>
            </div>
        </div>
    </div>
</section>
