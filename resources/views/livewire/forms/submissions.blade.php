<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="$form->name" :subtitle="__('Responses')">
        <x-slot:actions>
            <div class="flex gap-2">
                <flux:button variant="ghost" :href="route('clubs.forms', $club)" wire:navigate>{{ __('Back') }}</flux:button>
                @if ($form->audience === \App\Enums\FormAudience::Assigned)
                    <flux:modal.trigger name="assign-members">
                        <flux:button icon="user-plus">{{ __('Assign members') }}</flux:button>
                    </flux:modal.trigger>
                @endif
                <flux:button variant="primary" icon="pencil-square" :href="route('clubs.forms.edit', [$club, $form])" wire:navigate>
                    {{ __('Edit form') }}
                </flux:button>
            </div>
        </x-slot:actions>
    </x-portal.hero>

    @unless ($form->isPublished())
        <flux:callout icon="exclamation-triangle" color="amber">
            <flux:callout.heading>{{ __('This form is not published') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Members can only fill in a published form.') }}</flux:callout.text>
        </flux:callout>
    @endunless

    <div class="flex flex-wrap items-center gap-3">
        <flux:button size="sm" :variant="$tab === 'submitted' ? 'primary' : 'ghost'" wire:click="$set('tab', 'submitted')">
            {{ __('Completed') }}
        </flux:button>
        <flux:button size="sm" :variant="$tab === 'pending' ? 'primary' : 'ghost'" wire:click="$set('tab', 'pending')">
            {{ __('Outstanding') }}
        </flux:button>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name')" class="max-w-xs" />
    </div>

    @if ($tab === 'submitted')
        <x-table-card>
            <flux:table :paginate="$this->submissions">
                <flux:table.columns>
                    <flux:table.column>{{ __('Member') }}</flux:table.column>
                    <flux:table.column>{{ __('Filled in on') }}</flux:table.column>
                    <flux:table.column>{{ __('Version') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->submissions as $submission)
                        <flux:table.row :key="$submission->id">
                            <flux:table.cell class="sp-strong">{{ $submission->user->name }}</flux:table.cell>
                            <flux:table.cell>{{ $submission->submitted_at->timezone($club->timezone)->isoFormat('D MMMM YYYY HH:mm') }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-1.5">
                                    <flux:badge size="sm" :color="$submission->form_version_id === $form->published_version_id ? 'green' : 'zinc'">
                                        {{ __('Version :number', ['number' => $submission->version->version]) }}
                                    </flux:badge>
                                    @if ($submission->reopened_at)
                                        <flux:badge size="sm" color="amber">{{ __('Reopened') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <flux:button variant="ghost" size="sm" :href="route('clubs.forms.submissions.show', [$club, $form, $submission])" wire:navigate>
                                    {{ __('View') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-table-card>
    @else
        <x-table-card>
            <flux:table :paginate="$this->pendingMembers">
                <flux:table.columns>
                    <flux:table.column>{{ __('Member') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->pendingMembers as $member)
                        <flux:table.row :key="$member->id">
                            <flux:table.cell class="sp-strong">{{ $member->name }}</flux:table.cell>
                            <flux:table.cell>{{ $member->email }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-table-card>
    @endif

    @if ($form->audience === \App\Enums\FormAudience::Assigned)
        <flux:modal name="assign-members" class="md:w-[32rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Assign members') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Only assigned members see this form in their portal.') }}</flux:text>
                </div>

                <flux:input wire:model.live.debounce.300ms="assignSearch" icon="magnifying-glass" :placeholder="__('Search by name')" />

                <div class="flex max-h-80 flex-col gap-2 overflow-y-auto">
                    @foreach ($this->assignableMembers as $member)
                        <div wire:key="assignable-{{ $member->id }}" class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                            <flux:text class="truncate">{{ $member->name }}</flux:text>

                            @if (in_array($member->id, $this->assignedUserIds, true))
                                <flux:button size="sm" variant="ghost" wire:click="unassign({{ $member->id }})">{{ __('Remove') }}</flux:button>
                            @else
                                <flux:button size="sm" wire:click="assign({{ $member->id }})">{{ __('Assign') }}</flux:button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </flux:modal>
    @endif
</div>
