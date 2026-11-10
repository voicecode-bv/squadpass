@props([
    'except' => null,
    'title' => __('Built for your type of club'),
])

{{-- Links to the landing page per type of business --}}
<section class="py-16 lg:py-24">
    <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-4xl">{{ $title }}</h2>
        <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">{{ __('See how SquadPass works for your business.') }}</p>
    </div>

    <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach (\App\Enums\ClubSegment::cases() as $segment)
            @continue($segment === $except)
            <a href="{{ route('segments.show', $segment) }}" class="group flex flex-col rounded-2xl bg-white dark:bg-white/5 p-6 ring-1 ring-zinc-200 dark:ring-white/10 transition hover:bg-zinc-50 dark:hover:bg-white/10 hover:ring-blue-400/40">
                <span class="flex size-11 items-center justify-center rounded-xl bg-blue-500/15 text-blue-600 dark:text-blue-300 ring-1 ring-blue-400/30 transition group-hover:bg-blue-500 group-hover:text-white">
                    <flux:icon :icon="$segment->icon()" class="size-5" />
                </span>
                <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">{{ $segment->label() }}</h3>
                <p class="mt-2 flex-1 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $segment->cardText() }}</p>
                <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 dark:text-blue-300 transition group-hover:text-zinc-900 dark:group-hover:text-white">
                    {{ __('Read more') }}
                    <flux:icon.arrow-right variant="micro" class="size-4" />
                </span>
            </a>
        @endforeach
    </div>
</section>
