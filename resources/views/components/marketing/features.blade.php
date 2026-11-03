@props([
    'except' => null,
    'title' => __('Everything SquadPass does for your club'),
])

{{-- Links to the landing page per feature, for internal linking between the marketing pages. --}}
<section class="py-16 lg:py-24">
    <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $title }}</h2>
        <p class="mt-4 text-lg text-zinc-400">{{ __('Every feature is included in every plan.') }}</p>
    </div>

    <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (\App\Enums\MarketingFeature::cases() as $feature)
            @continue($feature === $except)
            <a href="{{ route('features.show', $feature) }}" class="group flex flex-col rounded-2xl bg-white/5 p-6 ring-1 ring-white/10 transition hover:bg-white/10 hover:ring-blue-400/40">
                <span class="flex size-11 items-center justify-center rounded-xl bg-blue-500/15 text-blue-300 ring-1 ring-blue-400/30 transition group-hover:bg-blue-500 group-hover:text-white">
                    <flux:icon :icon="$feature->icon()" class="size-5" />
                </span>
                <h3 class="mt-4 text-lg font-semibold text-white">{{ $feature->label() }}</h3>
                <p class="mt-2 flex-1 text-sm leading-relaxed text-zinc-400">{{ $feature->cardText() }}</p>
                <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-blue-300 transition group-hover:text-white">
                    {{ __('Read more') }}
                    <flux:icon.arrow-right variant="micro" class="size-4" />
                </span>
            </a>
        @endforeach
    </div>
</section>
