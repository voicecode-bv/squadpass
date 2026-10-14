<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Invoices')" :subtitle="$club->name">
        @if ($club->hasMoneybirdConfigured())
            <x-slot:actions>
                <flux:button wire:click="sync" icon="arrow-path" variant="primary">{{ __('Sync with Moneybird') }}</flux:button>
            </x-slot:actions>
        @endif
    </x-portal.hero>

    @if (! $club->hasAddon(\App\Enums\AddonKey::Moneybird))
        <livewire:clubs.addon-cta :club="$club" :addon-key="\App\Enums\AddonKey::Moneybird" wire:key="addon-cta-Moneybird" />
    @elseif (! $club->hasMoneybirdConfigured())
        <x-portal.empty icon="link" :heading="__('Connect your Moneybird administration')" :text="__('You are almost there: connect your administration to view and credit invoices here.')">
            @can('update', $club)
                <flux:button variant="primary" icon="link" :href="route('clubs.moneybird', $club)" wire:navigate>{{ __('Connect Moneybird') }}</flux:button>
            @endcan
        </x-portal.empty>
    @else
        <div class="sp-panel p-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:input wire:model.live.debounce.300ms="member" icon="user" :label="__('Member')" :placeholder="__('Name, email or number')" clearable />
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Invoice number')" :placeholder="__('e.g. 2026-0012')" clearable />

                <flux:field>
                    <flux:label>{{ __('Invoice date') }}</flux:label>
                    <div class="flex items-center gap-2">
                        <flux:input wire:model.live="from" type="date" :aria-label="__('From date')" />
                        <flux:input wire:model.live="until" type="date" :aria-label="__('Until date')" />
                    </div>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Amount') }} (&euro;)</flux:label>
                    <div class="flex items-center gap-2">
                        <flux:input wire:model.live.debounce.500ms="minAmount" inputmode="decimal" :placeholder="__('Min')" :aria-label="__('Minimum amount')" />
                        <flux:input wire:model.live.debounce.500ms="maxAmount" inputmode="decimal" :placeholder="__('Max')" :aria-label="__('Maximum amount')" />
                    </div>
                </flux:field>

                <flux:select wire:model.live="state" :label="__('Status')">
                    <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                    @foreach ($this->states as $invoiceState)
                        <flux:select.option :value="$invoiceState->value">{{ $invoiceState->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="type" :label="__('Type')">
                    <flux:select.option value="all">{{ __('All invoices') }}</flux:select.option>
                    <flux:select.option value="invoices">{{ __('Invoices only') }}</flux:select.option>
                    <flux:select.option value="credits">{{ __('Credit invoices only') }}</flux:select.option>
                </flux:select>

                <div class="flex items-end">
                    <flux:button wire:click="resetFilters" variant="ghost" icon="x-mark">{{ __('Clear filters') }}</flux:button>
                </div>
            </div>

            <flux:separator class="my-4" variant="subtle" />

            <flux:text>
                {{ trans_choice(':count invoice|:count invoices', $this->totals['count'], ['count' => $this->totals['count']]) }}
                &middot;
                {{ __('Total') }}: <span class="font-normal text-zinc-900 dark:text-white">&euro; {{ number_format($this->totals['total_cents'] / 100, 2, ',', '.') }}</span>
            </flux:text>
        </div>

        <x-table-card>
            <flux:table :paginate="$this->invoices">
                <flux:table.columns>
                    <flux:table.column>{{ __('Invoice') }}</flux:table.column>
                    <flux:table.column>{{ __('Member') }}</flux:table.column>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->invoices as $invoice)
                        <flux:table.row :key="$invoice->id">
                            <flux:table.cell class="sp-strong">
                                <div class="flex items-center gap-2">
                                    <span>{{ $invoice->invoice_number ?? __('Draft') }}</span>
                                    @if ($invoice->is_credit_invoice)
                                        <flux:badge size="sm" color="purple">{{ __('Credit') }}</flux:badge>
                                    @elseif ($invoice->credit_invoices_count > 0)
                                        <flux:badge size="sm" color="zinc">{{ __('Credited') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($invoice->membership)
                                    <flux:link :href="route('clubs.members.show', [$club, $invoice->membership])" wire:navigate variant="ghost">
                                        {{ $invoice->membership->user->name }}
                                    </flux:link>
                                @else
                                    <flux:text>{{ __('Unlinked contact') }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $invoice->invoice_date?->format('d-m-Y') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$invoice->state->color()">{{ $invoice->state->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $invoice->formattedTotal() }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                    <flux:menu>
                                        <flux:menu.item wire:click="showInvoice({{ $invoice->id }})" icon="eye">
                                            {{ __('View invoice') }}
                                        </flux:menu.item>
                                        @if ($invoice->isCreditable())
                                            <flux:menu.item wire:click="createCreditInvoice({{ $invoice->id }})" wire:confirm="{{ __('Create a credit invoice for this invoice in Moneybird?') }}" icon="receipt-refund">
                                                {{ __('Create credit invoice') }}
                                            </flux:menu.item>
                                        @endif
                                        @if ($invoice->moneybirdUrl())
                                            <flux:menu.item :href="$invoice->moneybirdUrl()" target="_blank" icon="arrow-top-right-on-square">
                                                {{ __('Open in Moneybird') }}
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6">
                                <flux:text>{{ __('No invoices found.') }}</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-table-card>
    @endif

    <flux:modal name="invoice-detail" class="md:w-[32rem]">
        @if ($invoice = $this->selectedInvoice)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">
                        {{ $invoice->is_credit_invoice ? __('Credit invoice') : __('Invoice') }}
                        {{ $invoice->invoice_number ?? __('(draft)') }}
                    </flux:heading>
                    <flux:text class="mt-1">
                        {{ $invoice->membership?->user->name ?? __('Unlinked contact') }}
                        &middot; {{ $invoice->invoice_date?->format('d-m-Y') ?? '—' }}
                        &middot; {{ $invoice->state->label() }}
                    </flux:text>
                </div>

                @if ($invoice->creditedInvoice)
                    <flux:callout icon="receipt-refund" color="zinc">
                        <flux:callout.text>{{ __('Credits invoice :number.', ['number' => $invoice->creditedInvoice->invoice_number ?? $invoice->creditedInvoice->moneybird_id]) }}</flux:callout.text>
                    </flux:callout>
                @endif

                @foreach ($invoice->creditInvoices as $creditInvoice)
                    <flux:callout icon="receipt-refund" color="zinc">
                        <flux:callout.text>{{ __('Credited by :number (:amount).', [
                            'number' => $creditInvoice->invoice_number ?? __('draft'),
                            'amount' => $creditInvoice->formattedTotal(),
                        ]) }}</flux:callout.text>
                    </flux:callout>
                @endforeach

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Description') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($invoice->lines ?? [] as $line)
                            <flux:table.row>
                                <flux:table.cell>{{ $line['description'] }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">&euro; {{ number_format($line['price_cents'] / 100, 2, ',', '.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                        <flux:table.row>
                            <flux:table.cell class="sp-strong">{{ __('Total incl. VAT') }}</flux:table.cell>
                            <flux:table.cell class="sp-strong" align="end" class="tabular-nums">{{ $invoice->formattedTotal() }}</flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>

                <div class="flex justify-end gap-2">
                    @if ($invoice->moneybirdUrl())
                        <flux:button :href="$invoice->moneybirdUrl()" target="_blank" variant="ghost" icon="arrow-top-right-on-square">
                            {{ __('Open in Moneybird') }}
                        </flux:button>
                    @endif
                    @if ($invoice->isCreditable())
                        <flux:button wire:click="createCreditInvoice({{ $invoice->id }})" variant="primary" icon="receipt-refund">
                            {{ __('Create credit invoice') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>
</div>
