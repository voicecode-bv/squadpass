<div class="flex w-full flex-col gap-6">
    <x-portal.hero
        :eyebrow="$ticket->reference()"
        :title="$ticket->subject"
        :subtitle="$ticket->club->name"
    >
        <x-slot:actions>
            <flux:button :href="route('portal.tickets')" wire:navigate variant="ghost" size="sm" icon="arrow-left">
                {{ __('Back to tickets') }}
            </flux:button>
        </x-slot:actions>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-portal.stat :value="$ticket->status->label()" :label="__('Status')" :delay="240" />
            <x-portal.stat :value="$ticket->priority->label()" :label="__('Priority')" :delay="290" />
            <x-portal.stat :value="$ticket->category->label()" :label="__('Category')" :delay="340" />
            <x-portal.stat :value="$ticket->created_at->timezone($ticket->club->timezone)->isoFormat('D MMM YYYY')" :label="__('Created')" :delay="390" />
        </div>
    </x-portal.hero>

    @if ($ticket->assignee)
        <div class="stagger animate-rise sp-panel flex items-center gap-3 px-4 py-3" style="--d: 100ms">
            <span class="text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">{{ __('Assignee') }}</span>
            <x-user-chip :user="$ticket->assignee" />
        </div>
    @endif

    <x-tickets.thread :ticket="$ticket" :messages="$this->messages" class="stagger animate-rise" style="--d: 160ms" />

    @if ($ticket->isClosed())
        <x-portal.empty icon="lock-closed" :heading="__('This ticket is closed.')" :text="__('Create a new ticket if you have another question.')">
            <flux:button variant="primary" icon="plus" :href="route('portal.tickets')" wire:navigate
                        >
                {{ __('Support tickets') }}
            </flux:button>
        </x-portal.empty>
    @else
        <form wire:submit="sendMessage"
              class="stagger animate-rise sp-panel flex flex-col gap-4 p-6"
              style="--d: 220ms">

            <flux:textarea wire:model="reply" rows="4" :placeholder="__('Write your reply...')" class="relative" />
            <div class="relative flex justify-end">
                <flux:button type="submit" variant="primary" icon="paper-airplane"
                            >
                    {{ __('Send reply') }}
                </flux:button>
            </div>
        </form>
    @endif
</div>
