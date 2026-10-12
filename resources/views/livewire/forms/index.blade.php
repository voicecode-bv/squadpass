<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Forms')" :subtitle="__('Build your own forms, for example an intake form for new members.')">
        <x-slot:actions>
            <flux:modal.trigger name="create-form">
                <flux:button variant="primary" icon="plus">{{ __('New form') }}</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-portal.hero>

    @if ($this->forms->isEmpty())
        <flux:callout icon="clipboard-document-list">
            <flux:callout.heading>{{ __('No forms yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Create a form, add fields and publish it so members can fill it in.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-table-card>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Version') }}</flux:table.column>
                    <flux:table.column>{{ __('Visible to') }}</flux:table.column>
                    <flux:table.column>{{ __('Responses') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->forms as $form)
                        <flux:table.row :key="$form->id">
                            <flux:table.cell class="sp-strong">{{ $form->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$form->status->color()">{{ $form->status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $form->publishedVersion ? __('Version :number', ['number' => $form->publishedVersion->version]) : __('Not published yet') }}
                            </flux:table.cell>
                            <flux:table.cell>{{ $form->audience->label() }}</flux:table.cell>
                            <flux:table.cell>{{ $form->current_submissions_count }}</flux:table.cell>
                            <flux:table.cell class="flex justify-end gap-2">
                                <flux:button variant="ghost" size="sm" icon="pencil-square" :href="route('clubs.forms.edit', [$club, $form])" wire:navigate>
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button variant="ghost" size="sm" icon="inbox" :href="route('clubs.forms.submissions', [$club, $form])" wire:navigate>
                                    {{ __('Responses') }}
                                </flux:button>
                                @if ($form->status === \App\Enums\FormStatus::Archived)
                                    <flux:button variant="ghost" size="sm" wire:click="restore({{ $form->id }})">{{ __('Restore') }}</flux:button>
                                @else
                                    <flux:button variant="ghost" size="sm" wire:click="archive({{ $form->id }})">{{ __('Archive') }}</flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-table-card>
    @endif

    <flux:modal name="create-form" class="md:w-96">
        <form wire:submit="createForm" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New form') }}</flux:heading>
                <flux:text class="mt-1">{{ __('You add the fields in the next step.') }}</flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('Intake form')" required autofocus />
            <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

            <flux:radio.group wire:model="audience" :label="__('Visible to')">
                @foreach (\App\Enums\FormAudience::cases() as $audience)
                    <flux:radio value="{{ $audience->value }}" :label="$audience->label()" />
                @endforeach
            </flux:radio.group>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Create form') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
