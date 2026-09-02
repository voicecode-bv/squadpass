<div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Moneybird') }}</flux:heading>
            <flux:text class="mt-1">{{ $club->name }}</flux:text>
        </div>
        <img src="{{ asset(\App\Enums\AddonKey::Moneybird->logoPath()) }}" alt="Moneybird" class="mt-1 h-6 w-auto invert dark:invert-0" />
    </div>

    @if (! $this->hasAddon)
        <flux:callout icon="document-text" variant="secondary">
            <flux:callout.heading>{{ __('Invoicing (Moneybird)') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Activate the Moneybird add-on to sync members as contacts and invoice paid payments automatically.') }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button :href="route('clubs.addons', $club)" wire:navigate size="sm">{{ __('View add-ons') }}</flux:button>
            </x-slot>
        </flux:callout>
    @elseif (! $this->isConnected)
        {{-- Step 1 / 2: connect --}}
        <div class="flex flex-col gap-6 rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <div>
                <flux:heading size="lg">{{ __('Connect your administration') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Create a personal API token in Moneybird under Settings, API & webhooks, with access to sales invoices, contacts and settings.') }}</flux:text>
            </div>

            @if ($administrations === [])
                <form wire:submit="connect" class="space-y-6">
                    <flux:input wire:model="access_token" :label="__('Moneybird access token')" type="password" viewable required />
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" icon="link">{{ __('Connect') }}</flux:button>
                    </div>
                </form>
            @else
                <form wire:submit="selectAdministration" class="space-y-6">
                    <flux:select wire:model="administration_id" :label="__('Administration')" :placeholder="__('Choose an administration')">
                        @foreach ($administrations as $administration)
                            <flux:select.option value="{{ $administration['id'] }}">{{ $administration['name'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <div class="flex justify-between">
                        <flux:button variant="ghost" wire:click="$set('administrations', [])">{{ __('Back') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Use this administration') }}</flux:button>
                    </div>
                </form>
            @endif
        </div>
    @else
        {{-- Connected: status --}}
        <div class="flex flex-col gap-4 rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ __('Connected') }}</flux:heading>
                        <flux:badge size="sm" color="green">{{ __('Administration :id', ['id' => $club->moneybird_administration_id]) }}</flux:badge>
                    </div>
                    <flux:text class="mt-1">
                        @if ($club->moneybird_last_synced_at)
                            {{ __('Last successful sync :time.', ['time' => $club->moneybird_last_synced_at->diffForHumans()]) }}
                        @else
                            {{ __('Nothing has been synced yet.') }}
                        @endif
                    </flux:text>
                </div>
                <flux:button size="sm" variant="ghost" icon="link-slash" wire:click="disconnect" wire:confirm="{{ __('Disconnect Moneybird? Existing invoices and contacts stay in Moneybird.') }}">{{ __('Disconnect') }}</flux:button>
            </div>

            @if ($club->moneybird_last_error)
                <flux:callout icon="exclamation-triangle" variant="danger">
                    <flux:callout.heading>{{ __('The last Moneybird request failed') }}</flux:callout.heading>
                    <flux:callout.text class="break-all">{{ $club->moneybird_last_error }}</flux:callout.text>
                </flux:callout>
            @endif
        </div>

        {{-- Invoice settings --}}
        <form wire:submit="saveSettings" class="flex flex-col gap-6 rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Invoices') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Every paid Mollie payment becomes a sales invoice in Moneybird with these settings.') }}</flux:text>
                </div>
                <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="refreshOptions">{{ __('Refresh') }}</flux:button>
            </div>

            <flux:radio.group wire:model="invoice_delivery" :label="__('After creating an invoice')">
                @foreach (\App\Enums\MoneybirdInvoiceDelivery::cases() as $delivery)
                    <flux:radio value="{{ $delivery->value }}" :label="$delivery->label()" :description="$delivery->description()" />
                @endforeach
            </flux:radio.group>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:select wire:model="ledger_account_id" :label="__('Revenue ledger account')" :placeholder="__('Moneybird default')">
                    <flux:select.option value="">{{ __('Moneybird default') }}</flux:select.option>
                    @foreach ($this->ledgerAccounts as $account)
                        <flux:select.option value="{{ $account['id'] }}">{{ $account['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="tax_rate_id" :label="__('Tax rate')" :placeholder="__('Moneybird default')">
                    <flux:select.option value="">{{ __('Moneybird default') }}</flux:select.option>
                    @foreach ($this->taxRates as $rate)
                        <flux:select.option value="{{ $rate['id'] }}">{{ $rate['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="workflow_id" :label="__('Workflow')" :placeholder="__('Moneybird default')">
                    <flux:select.option value="">{{ __('Moneybird default') }}</flux:select.option>
                    @foreach ($this->workflows as $workflow)
                        <flux:select.option value="{{ $workflow['id'] }}">{{ $workflow['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="document_style_id" :label="__('Document style')" :placeholder="__('Moneybird default')">
                    <flux:select.option value="">{{ __('Moneybird default') }}</flux:select.option>
                    @foreach ($this->documentStyles as $style)
                        <flux:select.option value="{{ $style['id'] }}">{{ $style['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:text class="text-xs">{{ __('Prices are sent including VAT, so the tax rate is deducted from the amount the member paid.') }}</flux:text>

            <flux:separator />

            <flux:switch wire:model="sync_contacts" :label="__('Sync members automatically')" :description="__('New members and changed names or email addresses are pushed to Moneybird as contacts right away.')" />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>

        {{-- Maintenance --}}
        <div class="flex flex-col gap-4 rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Catch up') }}</flux:heading>

            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:text class="font-medium text-zinc-900 dark:text-white">{{ trans_choice(':count paid payment without invoice|:count paid payments without invoice', $this->missingInvoiceCount) }}</flux:text>
                    <flux:text class="text-sm">{{ __('Payments made before Moneybird was connected, or that failed earlier.') }}</flux:text>
                </div>
                <flux:button size="sm" icon="document-plus" wire:click="invoiceMissingPayments" :disabled="$this->missingInvoiceCount === 0">{{ __('Create invoices') }}</flux:button>
            </div>

            <flux:separator />

            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:text class="font-medium text-zinc-900 dark:text-white">{{ trans_choice(':count active member without Moneybird contact|:count active members without Moneybird contact', $this->unsyncedMemberCount) }}</flux:text>
                    <flux:text class="text-sm">{{ __('Push all active members to Moneybird; existing contacts are updated.') }}</flux:text>
                </div>
                <flux:button size="sm" icon="users" wire:click="syncAllContacts">{{ __('Sync all members') }}</flux:button>
            </div>
        </div>
    @endif
</div>
