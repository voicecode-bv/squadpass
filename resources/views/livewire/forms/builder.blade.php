<div class="flex w-full flex-col gap-6">
    <x-portal.hero
        :title="$form->name"
        :subtitle="$form->publishedVersion
            ? __('Published version :number — you are editing draft version :draft.', ['number' => $form->publishedVersion->version, 'draft' => $draft->version])
            : __('Draft version :draft, not published yet.', ['draft' => $draft->version])"
    >
        <x-slot:actions>
            <flux:button variant="ghost" :href="route('clubs.forms', $club)" wire:navigate>{{ __('Back') }}</flux:button>
            <flux:button variant="primary" icon="check" wire:click="publish">{{ __('Publish') }}</flux:button>
        </x-slot:actions>
    </x-portal.hero>

    @if ($form->publishedVersion)
        <flux:callout icon="information-circle" color="zinc">
            <flux:callout.text>
                {{ __('Changes only apply to forms filled in from now on. Responses already given keep the version they were filled in with.') }}
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-4 lg:col-span-2">
            <div class="sp-panel p-5">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Fields') }}</flux:heading>

                    <flux:dropdown position="bottom" align="end">
                        <flux:button icon="plus" size="sm">{{ __('Add field') }}</flux:button>

                        <flux:menu>
                            @foreach ($this->fieldTypes as $value => $label)
                                <flux:menu.item wire:click="newField('{{ $value }}')">{{ $label }}</flux:menu.item>
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                </div>

                @if ($this->fields === [])
                    <flux:text class="py-6 text-center">{{ __('No fields yet. Add your first field.') }}</flux:text>
                @else
                    <div class="mt-4 flex flex-col gap-2">
                        @foreach ($this->fields as $index => $field)
                            <div wire:key="field-{{ $field->key }}" class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <flux:text class="truncate font-normal">{{ $field->label }}</flux:text>
                                        @if ($field->required)
                                            <flux:badge size="sm" color="amber">{{ __('Required') }}</flux:badge>
                                        @endif
                                    </div>
                                    <flux:text size="sm" class="truncate">{{ $field->type->label() }}</flux:text>
                                </div>

                                <div class="flex shrink-0 items-center gap-1">
                                    <flux:button variant="ghost" size="sm" icon="chevron-up" wire:click="moveField('{{ $field->key }}', -1)" :disabled="$index === 0" />
                                    <flux:button variant="ghost" size="sm" icon="chevron-down" wire:click="moveField('{{ $field->key }}', 1)" :disabled="$index === count($this->fields) - 1" />
                                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="editField('{{ $field->key }}')" />
                                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="deleteField('{{ $field->key }}')" wire:confirm="{{ __('Remove this field from the draft?') }}" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="sp-panel p-5">
                <flux:heading size="lg">{{ __('Preview') }}</flux:heading>

                <div class="mt-4 flex flex-col gap-5">
                    @foreach ($this->fields as $field)
                        <x-form-field :key="'preview-'.$field->key" :field="$field" :model="null" />
                    @endforeach
                </div>
            </div>
        </div>

        <div class="sp-panel h-fit p-5">
            <form wire:submit="saveSettings" class="space-y-6">
                <flux:heading size="lg">{{ __('Settings') }}</flux:heading>

                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

                <flux:radio.group wire:model="audience" :label="__('Visible to')">
                    @foreach (\App\Enums\FormAudience::cases() as $audience)
                        <flux:radio value="{{ $audience->value }}" :label="$audience->label()" />
                    @endforeach
                </flux:radio.group>

                <flux:button type="submit" variant="filled" class="w-full">{{ __('Save') }}</flux:button>
            </form>
        </div>
    </div>

    <flux:modal name="field-editor" class="md:w-[32rem]">
        <form wire:submit="saveField" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingKey !== '' ? __('Edit field') : __('New field') }}</flux:heading>
                <flux:text class="mt-1">{{ \App\Enums\FormFieldType::from($fieldType)->label() }}</flux:text>
            </div>

            <flux:input wire:model="fieldLabel" :label="__('Label')" required />

            <flux:textarea
                wire:model="fieldHelp"
                :label="\App\Enums\FormFieldType::from($fieldType) === \App\Enums\FormFieldType::Consent ? __('Statement text') : __('Help text')"
                rows="2"
            />

            @if (\App\Enums\FormFieldType::from($fieldType)->hasOptions())
                <div class="flex flex-col gap-2">
                    <flux:label>{{ __('Options') }}</flux:label>

                    @foreach ($fieldOptions as $index => $option)
                        <div wire:key="option-{{ $option['value'] }}" class="flex items-center gap-2">
                            <flux:input wire:model="fieldOptions.{{ $index }}.label" class="flex-1" />
                            <flux:button variant="ghost" size="sm" icon="trash" wire:click="removeOption({{ $index }})" />
                        </div>
                    @endforeach

                    <flux:error name="fieldOptions" />

                    <div>
                        <flux:button size="sm" icon="plus" wire:click="addOption">{{ __('Add option') }}</flux:button>
                    </div>
                </div>
            @endif

            @if (\App\Enums\FormFieldType::from($fieldType) !== \App\Enums\FormFieldType::Heading)
                <flux:switch wire:model="fieldRequired" :label="__('Required')" />
            @endif

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Save field') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
