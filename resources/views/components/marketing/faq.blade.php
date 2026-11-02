@props([
    /** @var list<array{question: string, answer: string}> $items */
    'items',
])

<section id="faq" class="scroll-mt-8 py-16 lg:py-24">
    <div class="mx-auto max-w-2xl text-center">
        <span class="inline-flex items-center gap-2 rounded-full bg-white/5 px-4 py-1.5 text-sm font-medium text-blue-300 ring-1 ring-white/10">
            {{ __('Frequently asked questions') }}
        </span>
        <h2 class="mt-6 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ __('Good to know before you start') }}</h2>
        <p class="mt-4 text-lg text-zinc-400">
            {{ __('The questions new clubs ask us most. Is yours not listed?') }}
            <a href="mailto:{{ config('mail.from.address') }}" class="font-semibold text-blue-300 transition hover:text-white">{{ __('Send us an email') }}</a>
        </p>
    </div>

    <div class="mx-auto mt-14 max-w-3xl space-y-4">
        @foreach ($items as $faq)
            <details class="group rounded-2xl bg-white/5 ring-1 ring-white/10 transition open:bg-white/[0.07]">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-6 px-6 py-5 text-left font-semibold text-white [&::-webkit-details-marker]:hidden">
                    {{ $faq['question'] }}
                    <flux:icon.plus class="size-5 shrink-0 text-blue-300 transition group-open:rotate-45" />
                </summary>
                <p class="px-6 pb-6 leading-relaxed text-zinc-400">{{ $faq['answer'] }}</p>
            </details>
        @endforeach
    </div>

    {{-- FAQ structured data, so search engines can show the answers directly --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $faq): array => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
            ], $items),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
    </script>
</section>
