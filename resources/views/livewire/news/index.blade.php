<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('News')" :subtitle="__('Messages for the members of :club, shown in their news feed.', ['club' => $club->name])">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="newPost">{{ __('New post') }}</flux:button>
        </x-slot:actions>
    </x-portal.hero>

    @if ($this->posts->isEmpty())
        <x-portal.empty icon="megaphone" :heading="__('No news yet')" :text="__('Share news, schedule changes or events with your members. They see it in their news feed.')" />
    @else
        <x-table-card>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Title') }}</flux:table.column>
                    <flux:table.column>{{ __('Author') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->posts->take($limit) as $post)
                        <flux:table.row :key="'post-'.$post->id">
                            <flux:table.cell class="sp-strong max-w-96 truncate">
                                {{ $post->title }}
                                @if ($post->pinned)
                                    <span class="sp-muted font-normal">· {{ __('Pinned') }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $post->author?->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($post->isDraft())
                                    <flux:badge size="sm" color="zinc">{{ __('Draft') }}</flux:badge>
                                @elseif ($post->isScheduled())
                                    <flux:badge size="sm" color="amber">{{ __('Scheduled for :date', ['date' => $post->published_at->timezone($club->timezone)->isoFormat('D MMM HH:mm')]) }}</flux:badge>
                                @else
                                    {{ $post->published_at->timezone($club->timezone)->isoFormat('D MMM YYYY HH:mm') }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />

                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="editPost({{ $post->id }})">{{ __('Edit') }}</flux:menu.item>
                                        <flux:menu.item icon="map-pin" wire:click="togglePinned({{ $post->id }})">{{ $post->pinned ? __('Unpin') : __('Pin to top') }}</flux:menu.item>
                                        <flux:menu.item icon="trash" variant="danger" wire:click="deletePost({{ $post->id }})" wire:confirm="{{ __('Delete this post? Members no longer see it.') }}">{{ __('Delete') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-table-card>

        @if ($this->posts->count() > $limit)
            <div>
                <flux:button size="sm" variant="ghost" wire:click="showMore">{{ __('Show more') }}</flux:button>
            </div>
        @endif
    @endif

    <flux:modal name="news-post" class="w-full md:w-[36rem]">
        <form wire:submit="savePost" class="space-y-6">
            <flux:heading size="lg">{{ $editingPostId ? __('Edit post') : __('New post') }}</flux:heading>

            <flux:input wire:model="title" :label="__('Title')" required autofocus />
            <flux:textarea wire:model="body" :label="__('Message')" rows="8" required />

            <flux:radio.group wire:model.live="publication" :label="__('Publication')" variant="segmented">
                <flux:radio value="now" :label="__('Publish now')" />
                <flux:radio value="schedule" :label="__('Schedule for later')" />
                <flux:radio value="draft" :label="__('Draft')" />
            </flux:radio.group>

            @if ($publication === 'schedule')
                <flux:input type="datetime-local" wire:model="scheduledFor" :label="__('Publish on')" :description="__('In the club timezone (:timezone).', ['timezone' => $club->timezone])" required />
            @endif

            <flux:switch wire:model="pinned" :label="__('Pin to top')" :description="__('Pinned posts stay at the top of the news feed.')" />

            @if ($membersNotifiedAt)
                <flux:text class="text-sm">{{ __('Members were emailed about this post on :date.', ['date' => $membersNotifiedAt]) }}</flux:text>
            @else
                <flux:switch wire:model="notify_members" :label="__('Email members')" :description="__('Every active member gets an email once the post is published.')" />
            @endif

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">
                    {{ match ($publication) { 'draft' => __('Save draft'), 'schedule' => __('Schedule for later'), default => $editingPostId ? __('Save changes') : __('Publish') } }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
