<div class="flex w-full flex-col gap-6">
    <x-portal.hero :eyebrow="__('Your clubs')" :title="__('News')" :subtitle="__('News and announcements from your clubs.')" />

    @if ($this->pinnedPosts->isEmpty() && $this->timeline->isEmpty())
        <x-portal.empty icon="megaphone" :heading="__('No news yet')" :text="__('When your club posts news, you will find it here.')" />
    @else
        @foreach ($this->pinnedPosts as $post)
            <article wire:key="pinned-{{ $post->id }}" class="sp-panel animate-rise border-s-4 border-blue-600 p-5" data-test="pinned-post">
                <p class="sp-card-subtitle tracking-wide uppercase">{{ __('Pinned') }} · {{ $post->club->name }}</p>
                <h2 class="sp-card-title mt-1">{{ $post->title }}</h2>
                <div class="mt-3 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">{!! nl2br(e($post->body)) !!}</div>
                <p class="sp-muted mt-3 text-xs">{{ $post->published_at->timezone($post->club->timezone)->isoFormat('D MMMM YYYY') }}@if ($post->author) · {{ $post->author->name }}@endif</p>
            </article>
        @endforeach

        @if ($this->timeline->isNotEmpty())
            <ol class="relative flex flex-col gap-6 border-s border-zinc-200 ps-6 sm:ms-2 dark:border-zinc-700" data-test="news-timeline">
                @foreach ($this->timeline->take($limit) as $post)
                    @php($publishedAt = $post->published_at->timezone($post->club->timezone))

                    <li wire:key="post-{{ $post->id }}" class="stagger animate-rise relative" style="--d: {{ min(400, $loop->index * 50) }}ms">
                        <span class="absolute -start-[1.85rem] top-5 size-3 rounded-full bg-blue-600 ring-4 ring-canvas"></span>

                        <article class="sp-panel p-5">
                            <div class="flex items-center gap-3">
                                <flux:avatar size="xs" :src="$post->club->photoUrl()" :name="$post->club->name" />
                                <p class="sp-muted min-w-0 truncate text-xs">
                                    <span class="sp-strong">{{ $post->club->name }}</span>
                                    · <time datetime="{{ $post->published_at->toIso8601String() }}" title="{{ $publishedAt->isoFormat('D MMMM YYYY HH:mm') }}">{{ $publishedAt->isToday() ? $publishedAt->format('H:i') : $publishedAt->isoFormat('D MMMM YYYY') }}</time>
                                    @if ($post->author) · {{ $post->author->name }}@endif
                                </p>
                            </div>
                            <h2 class="mt-3 text-base font-semibold text-zinc-900 dark:text-white">{{ $post->title }}</h2>
                            <div class="mt-2 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">{!! nl2br(e($post->body)) !!}</div>
                        </article>
                    </li>
                @endforeach
            </ol>

            @if ($this->timeline->count() > $limit)
                <div class="ps-6">
                    <flux:button size="sm" variant="ghost" wire:click="showMore">{{ __('Show more') }}</flux:button>
                </div>
            @endif
        @endif
    @endif
</div>
