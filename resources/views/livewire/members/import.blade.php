<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Import members')" :subtitle="$club->name">
        <x-slot:actions>
            <flux:button :href="route('clubs.members', $club)" wire:navigate variant="ghost" icon="arrow-left">{{ __('Back to members') }}</flux:button>
        </x-slot:actions>
    </x-portal.hero>

    @if ($this->memberImport)
        {{-- Progress and report --}}
        @php($memberImport = $this->memberImport)

        <section class="sp-panel flex flex-col gap-6 p-5" @unless ($memberImport->status->isFinished()) wire:poll.2s @endunless>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="sp-card-title">{{ $memberImport->original_name }}</h2>
                        <flux:badge size="sm" :color="match ($memberImport->status) {
                            \App\Enums\MemberImportStatus::Completed => 'green',
                            \App\Enums\MemberImportStatus::Failed => 'red',
                            default => 'blue',
                        }">{{ $memberImport->status->label() }}</flux:badge>
                    </div>
                    <flux:text class="mt-1">
                        {{ trans_choice(':count row processed of :total|:count rows processed of :total', $memberImport->processed_rows, ['total' => $memberImport->total_rows]) }}
                        @if ($memberImport->send_invitations)
                            · {{ __('Invitations are being sent') }}
                        @endif
                    </flux:text>
                </div>

                @if ($memberImport->status->isFinished())
                    <flux:button variant="primary" icon="arrow-path" wire:click="startOver">{{ __('Import another list') }}</flux:button>
                @endif
            </div>

            @unless ($memberImport->status->isFinished())
                <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div class="h-full rounded-full bg-blue-600 transition-all" style="width: {{ $memberImport->progressPercentage() }}%"></div>
                </div>
            @endunless

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    [__('New accounts'), $memberImport->created_count],
                    [__('Existing accounts added'), $memberImport->linked_count],
                    [__('Skipped'), $memberImport->skipped_count],
                    [__('Not imported'), $memberImport->failed_count],
                ] as [$label, $count])
                    <div>
                        <p class="text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">{{ $label }}</p>
                        <p class="mt-2 text-2xl text-zinc-800 dark:text-white">{{ $count }}</p>
                    </div>
                @endforeach
            </div>

            <flux:text class="text-sm">{{ __('Skipped: already a member of this club, or no room left within the member limit of your plan.') }}</flux:text>
        </section>

        @if (filled($memberImport->row_errors))
            <section class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Rows that need attention') }}</flux:heading>

                <x-table-card>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Row') }}</flux:table.column>
                            <flux:table.column>{{ __('Problem') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($memberImport->row_errors as $error)
                                <flux:table.row :key="'error-'.$loop->index">
                                    <flux:table.cell class="sp-strong">{{ $error['row'] ?: '—' }}</flux:table.cell>
                                    <flux:table.cell>{{ $error['message'] }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </x-table-card>
            </section>
        @endif
    @elseif ($headers !== [])
        {{-- Match columns --}}
        <form wire:submit="start" class="flex flex-col gap-6">
            <section class="sp-panel flex flex-col gap-6 p-5">
                <div>
                    <h2 class="sp-card-title">{{ __('Match the columns') }}</h2>
                    <flux:text class="mt-1">{{ __('We matched what we recognised. Check it, and choose "Not imported" for columns you do not need.') }}</flux:text>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach (\App\Services\MemberImports\MemberCsv::FIELDS as $field)
                        <flux:select wire:model="columnMap.{{ $field }}" :label="\App\Services\MemberImports\MemberCsv::label($field)" wire:key="column-{{ $field }}">
                            <flux:select.option value="">{{ __('Not imported') }}</flux:select.option>
                            @foreach ($headers as $index => $header)
                                <flux:select.option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :number', ['number' => $index + 1]) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endforeach
                </div>

                <flux:error name="columnMap.name" />

                <flux:text class="text-sm">{{ __('Use a full name column, or first and last name. Dates like 31-12-1990 and 1990-12-31 are both fine.') }}</flux:text>
            </section>

            @if ($preview !== [])
                <section class="flex flex-col gap-4">
                    <flux:heading size="lg">{{ __('Preview') }}</flux:heading>

                    <x-table-card>
                        <flux:table>
                            <flux:table.columns>
                                @foreach ($headers as $index => $header)
                                    <flux:table.column>{{ $header !== '' ? $header : __('Column :number', ['number' => $index + 1]) }}</flux:table.column>
                                @endforeach
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($preview as $rowIndex => $row)
                                    <flux:table.row :key="'preview-'.$rowIndex">
                                        @foreach ($headers as $index => $header)
                                            <flux:table.cell>{{ $row[$index] ?? '' }}</flux:table.cell>
                                        @endforeach
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </x-table-card>
                </section>
            @endif

            <section class="sp-panel flex flex-col gap-6 p-5">
                <h2 class="sp-card-title">{{ __('Invitation') }}</h2>

                <flux:switch wire:model="send_invitations" :label="__('Send an invitation email')" :description="__('New members get an email to choose a password; people who already have an account hear that they were added. Without invitations, members can still log in later via \'Forgot password\'.')" />
            </section>

            <div class="flex flex-wrap justify-end gap-2">
                <flux:button type="button" variant="ghost" icon="arrow-left" wire:click="resetUpload">{{ __('Choose another file') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="user-plus">{{ __('Start import') }}</flux:button>
            </div>
        </form>
    @else
        {{-- Upload --}}
        <section class="sp-panel flex flex-col gap-6 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="sp-card-title">{{ __('Upload your member list') }}</h2>
                    <flux:text class="mt-1">{{ __('Export your members from your current system or spreadsheet as a CSV file. A header row is required; email and name are the only required columns.') }}</flux:text>
                </div>
                <flux:button size="sm" icon="arrow-down-tray" wire:click="downloadTemplate">{{ __('Download example') }}</flux:button>
            </div>

            <flux:text class="text-sm">
                {{ __('Supported columns') }}:
                {{ collect(\App\Services\MemberImports\MemberCsv::FIELDS)->map(fn ($field) => \App\Services\MemberImports\MemberCsv::label($field))->implode(', ') }}.
            </flux:text>

            <flux:input type="file" wire:model="file" accept=".csv,text/csv" :label="__('CSV file')" :description="__('Up to 10 MB.')" />

            <div wire:loading wire:target="file">
                <flux:text class="text-sm">{{ __('Reading the file…') }}</flux:text>
            </div>
        </section>
    @endif
</div>
