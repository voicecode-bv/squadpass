@props(['addonPrices'])

<section id="add-ons" class="scroll-mt-8 py-16 lg:py-24">
    <div class="mx-auto max-w-2xl text-center">
        <span class="inline-flex items-center gap-2 rounded-full bg-blue-500/10 px-4 py-1.5 text-sm font-medium text-blue-300 ring-1 ring-blue-400/30">
            <flux:icon.puzzle-piece variant="micro" class="size-4" />
            {{ __('Add-ons & integrations') }}
        </span>
        <h2 class="mt-6 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ __('Connect SquadPass with the tools that make your club stronger') }}</h2>
        <p class="mt-4 text-lg text-zinc-400">{{ __('Your plan covers the essentials, so switch on extras the moment you need them. Every add-on is billed per month and can be cancelled monthly.') }}</p>
    </div>

    <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            [
                'key' => \App\Enums\AddonKey::Mollie,
                'category' => __('Payments & direct debit'),
                'title' => __('Online payments with Mollie'),
                'text' => __('Members pay for strip cards and subscriptions online through your own Mollie account. Recurring charges run automatically via direct debit.'),
                'badges' => [__('iDEAL & cards'), __('SEPA direct debit'), __('Failed charge alerts')],
            ],
            [
                'key' => \App\Enums\AddonKey::Moneybird,
                'category' => __('Accounting & invoicing'),
                'title' => __('Invoicing with Moneybird'),
                'text' => __('Every member becomes a Moneybird contact and every paid payment turns into a draft sales invoice, ready to send from your own administration.'),
                'badges' => [__('Contact sync'), __('Draft invoices'), __('Member numbers as customer IDs')],
            ],
            [
                'key' => \App\Enums\AddonKey::MobileApp,
                'category' => __('Your own app'),
                'title' => __('Branded mobile app'),
                'text' => __('Your club in the App Store and Google Play, with your own logo and colours. Members book lessons, check their strip card and check in from their phone.'),
                'badges' => [__('iOS & Android'), __('Your logo and colours'), __('Push notifications')],
            ],
        ] as $item)
            @php($priceCents = $addonPrices[$item['key']->value] ?? null)
            <div class="group relative flex flex-col gap-5 overflow-hidden rounded-3xl bg-white/5 p-8 ring-1 ring-white/10 transition hover:bg-white/10 hover:ring-blue-400/40">
                <div class="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full bg-blue-500/10 blur-2xl transition group-hover:bg-blue-500/20" aria-hidden="true"></div>

                <div class="relative flex items-start justify-between gap-4">
                    @if ($item['key']->logoPath() !== null)
                        <img src="{{ asset($item['key']->logoPath()) }}" alt="{{ $item['title'] }}" class="h-7 w-auto" loading="lazy" />
                    @else
                        <span class="flex size-9 items-center justify-center rounded-xl bg-blue-500/15 text-blue-300 ring-1 ring-blue-400/30">
                            <flux:icon :icon="$item['key']->icon()" class="size-5" />
                        </span>
                    @endif
                    <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-medium tracking-wide text-zinc-300 ring-1 ring-white/10">{{ $item['category'] }}</span>
                </div>

                <div class="relative">
                    <h3 class="text-xl font-semibold text-white">{{ $item['title'] }}</h3>
                    <p class="mt-2 leading-relaxed text-zinc-400">{{ $item['text'] }}</p>
                </div>

                <div class="relative flex flex-col items-start gap-2">
                    @foreach ($item['badges'] as $badge)
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-500/10 px-2.5 py-1 text-xs font-medium text-blue-200 ring-1 ring-blue-400/20">
                            <flux:icon.check variant="micro" class="size-3.5" />
                            {{ $badge }}
                        </span>
                    @endforeach
                </div>

                <div class="relative mt-auto flex items-end justify-between gap-4 border-t border-white/10 pt-5">
                    <div>
                        @if ($priceCents !== null)
                            <p class="text-2xl font-semibold text-white">{{ \App\Models\Addon::formatCents($priceCents) }}<span class="text-sm font-normal text-zinc-400"> / {{ __('month') }}</span></p>
                            <p class="mt-1 text-xs text-zinc-500">{{ __('Cancel monthly, no minimum term') }}</p>
                        @else
                            <p class="text-sm font-medium text-zinc-300">{{ __('Coming soon') }}</p>
                        @endif
                    </div>
                    <a href="#signup" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-300 transition hover:text-white">
                        {{ __('Activate in your club') }}
                        <flux:icon.arrow-right variant="micro" class="size-4" />
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    @foreach (\App\Models\Addon::landingBundles() as $bundle)
        <div class="mt-6 flex flex-col gap-6 rounded-3xl bg-blue-600/20 p-8 ring-1 ring-blue-400/40 md:flex-row md:items-center md:justify-between">
            <div>
                <span class="text-xs font-semibold tracking-wider text-blue-200 uppercase">{{ __('Combine and save') }}</span>
                <h3 class="mt-2 text-xl font-semibold text-white">{{ $bundle['name'] }}</h3>
                <p class="mt-2 leading-relaxed text-zinc-300">{{ $bundle['description'] ?: __('Includes :addons', ['addons' => implode(' & ', $bundle['included'])]) }}</p>
            </div>
            <div class="shrink-0 md:text-right">
                <p class="text-2xl font-semibold text-white">{{ \App\Models\Addon::formatCents($bundle['price_cents']) }}<span class="text-sm font-normal text-zinc-400"> / {{ __('month') }}</span></p>
                @if ($bundle['separate_price_cents'] > $bundle['price_cents'])
                    <p class="mt-1 text-xs text-zinc-400">{{ __('instead of :price separately', ['price' => \App\Models\Addon::formatCents($bundle['separate_price_cents'])]) }}</p>
                @endif
            </div>
        </div>
    @endforeach

    <div class="mt-10 grid gap-6 rounded-3xl bg-white/5 p-8 ring-1 ring-white/10 sm:grid-cols-3">
        @foreach ([
            ['icon' => 'cursor-arrow-rays', 'title' => __('Activate in one click'), 'text' => __('Buy an add-on from your club settings and pay securely via Mollie. It is live the moment the payment succeeds.')],
            ['icon' => 'arrow-path', 'title' => __('Cancel monthly'), 'text' => __('No long contracts. Cancel whenever you like and keep using the add-on until the end of the paid month.')],
            ['icon' => 'sparkles', 'title' => __('More on the way'), 'text' => __('We keep adding integrations. Missing one your club relies on? Let us know.')],
        ] as $point)
            <div class="flex gap-4">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/15 text-blue-300 ring-1 ring-blue-400/30">
                    <flux:icon :icon="$point['icon']" class="size-5" />
                </span>
                <div>
                    <h3 class="font-semibold text-white">{{ $point['title'] }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-zinc-400">{{ $point['text'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>
