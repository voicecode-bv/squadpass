<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Moneybird')" :subtitle="$club->name">
        <x-slot:actions>
            <img src="{{ asset(\App\Enums\AddonKey::Moneybird->logoPath()) }}" alt="Moneybird" class="h-6 w-auto invert dark:invert-0" />
        </x-slot:actions>
    </x-portal.hero>

    @if (! $this->hasAddon)
        <livewire:clubs.addon-cta :club="$club" :addon-key="\App\Enums\AddonKey::Moneybird" wire:key="addon-cta-Moneybird" />
    @elseif (! $this->isConnected && ! $this->canManageIntegrations)
        <flux:callout icon="lock-closed" variant="secondary">
            <flux:callout.heading>{{ __('Not connected yet.') }}</flux:callout.heading>
            <flux:callout.text>{{ __('This is a demo club: SquadPass manages the Mollie and Moneybird settings.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($step > 0)
        {{-- Setup wizard --}}
        <div class="sp-panel flex flex-col gap-8 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading class="sp-card-title">{{ __('Set up Moneybird') }}</flux:heading>
                    <flux:text class="sp-card-subtitle mt-1">{{ __('Step :current of :total', ['current' => $step, 'total' => \App\Livewire\Clubs\Moneybird::LAST_STEP]) }} — {{ $this->stepLabels[$step] }}</flux:text>
                </div>
                @if ($this->isConnected)
                    <flux:badge size="sm" color="green">{{ __('Connected') }} · {{ __('Administration :id', ['id' => $club->moneybird_administration_id]) }}</flux:badge>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @foreach ($this->stepLabels as $number => $label)
                    <div class="flex flex-1 flex-col items-center gap-1" wire:key="moneybird-step-{{ $number }}">
                        <div @class([
                            'h-1.5 w-full rounded-full',
                            'bg-blue-600 dark:bg-blue-500' => $number <= $step,
                            'bg-zinc-200 dark:bg-zinc-700' => $number > $step,
                        ])></div>
                        <flux:text class="text-xs {{ $number === $step ? 'font-semibold' : '' }}">{{ $label }}</flux:text>
                    </div>
                @endforeach
            </div>

            @if ($step === 1)
                <form wire:submit="connect" class="flex flex-col gap-6">
                    <div>
                        <flux:heading size="lg">{{ __('Connect your administration') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Once connected, every paid payment becomes a sales invoice in Moneybird and your members can be kept in sync as contacts.') }}</flux:text>
                    </div>

                    <ol class="flex flex-col gap-3">
                        @foreach ([
                            __('Log in to Moneybird and open the administration of your club.'),
                            __('Go to Settings and then Integrations.'),
                            __('Under External applications, click the Create API token button to create a new token.'),
                            __('Give the token access to sales invoices, contacts and settings.'),
                            __('Copy the token and paste it below.'),
                        ] as $instructionIndex => $instruction)
                            <li class="flex items-start gap-3" wire:key="moneybird-instruction-{{ $instructionIndex }}">
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-semibold text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">{{ $instructionIndex + 1 }}</span>
                                <flux:text class="pt-0.5">{{ $instruction }}</flux:text>
                            </li>
                        @endforeach
                    </ol>

                    <flux:input wire:model="access_token" :label="__('Moneybird access token')" type="password" viewable required />

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" icon-trailing="arrow-right">{{ __('Connect') }}</flux:button>
                    </div>
                </form>
            @elseif ($step === 2)
                <form wire:submit="selectAdministration" class="flex flex-col gap-6">
                    <div>
                        <flux:heading size="lg">{{ __('Choose an administration') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Your token has access to several administrations. Pick the one your club bookkeeps in.') }}</flux:text>
                    </div>

                    <flux:radio.group wire:model="administration_id" :label="__('Administration')">
                        @foreach ($administrations as $administration)
                            <flux:radio value="{{ $administration['id'] }}" :label="$administration['name']" wire:key="administration-{{ $administration['id'] }}" />
                        @endforeach
                    </flux:radio.group>

                    <div class="flex justify-between">
                        <flux:button type="button" variant="ghost" icon="arrow-left" wire:click="previousStep">{{ __('Back') }}</flux:button>
                        <flux:button type="submit" variant="primary" icon-trailing="arrow-right">{{ __('Next') }}</flux:button>
                    </div>
                </form>
            @elseif ($step === 3)
                <form wire:submit="saveAccounting" class="flex flex-col gap-6">
                    <div>
                        <flux:heading size="lg">{{ __('Where is the revenue booked?') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Choose the revenue ledger account and VAT rate for invoices from SquadPass. Not sure? Leave them on the Moneybird default and ask your bookkeeper later.') }}</flux:text>
                    </div>

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
                    </div>

                    <flux:text class="text-xs">{{ __('Prices are sent including VAT, so the tax rate is deducted from the amount the member paid.') }}</flux:text>

                    <div class="flex justify-between">
                        <flux:button type="button" variant="ghost" icon="arrow-path" wire:click="refreshOptions">{{ __('Refresh') }}</flux:button>
                        <flux:button type="submit" variant="primary" icon-trailing="arrow-right">{{ __('Next') }}</flux:button>
                    </div>
                </form>
            @elseif ($step === 4)
                <form wire:submit="saveInvoicing" class="flex flex-col gap-6">
                    <div>
                        <flux:heading size="lg">{{ __('What happens with an invoice?') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Decide whether SquadPass only prepares invoices or also finalizes and sends them.') }}</flux:text>
                    </div>

                    <flux:radio.group wire:model="invoice_delivery" :label="__('After creating an invoice')">
                        @foreach (\App\Enums\MoneybirdInvoiceDelivery::cases() as $delivery)
                            <flux:radio value="{{ $delivery->value }}" :label="$delivery->label()" :description="$delivery->description()" />
                        @endforeach
                    </flux:radio.group>

                    <div class="grid gap-6 sm:grid-cols-2">
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

                    <div class="flex justify-between">
                        <flux:button type="button" variant="ghost" icon="arrow-left" wire:click="previousStep">{{ __('Back') }}</flux:button>
                        <flux:button type="submit" variant="primary" icon-trailing="arrow-right">{{ __('Next') }}</flux:button>
                    </div>
                </form>
            @else
                <form wire:submit="finishSetup" class="flex flex-col gap-6">
                    <div>
                        <flux:heading size="lg">{{ __('Members and existing data') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Almost done. Choose how members are kept in sync and whether SquadPass should catch up on what happened before the connection.') }}</flux:text>
                    </div>

                    <flux:switch wire:model="sync_contacts" :label="__('Sync members automatically')" :description="__('New members and changed names or email addresses are pushed to Moneybird as contacts right away.')" />

                    <flux:separator />

                    <div class="flex flex-col gap-4">
                        @if ($this->missingInvoiceCount > 0)
                            <flux:checkbox wire:model="invoice_existing_payments"
                                :label="trans_choice('Create an invoice for :count earlier payment|Create invoices for :count earlier payments', $this->missingInvoiceCount)"
                                :description="__('Paid payments from before the connection get an invoice in Moneybird too.')" />
                        @endif

                        @if ($this->unsyncedMemberCount > 0)
                            <flux:checkbox wire:model="sync_existing_members"
                                :label="trans_choice('Add :count existing member as a contact|Add :count existing members as contacts', $this->unsyncedMemberCount)"
                                :description="__('Members who joined before the connection are added to Moneybird now.')" />
                        @endif

                        @if ($this->missingInvoiceCount === 0 && $this->unsyncedMemberCount === 0)
                            <flux:text>{{ __('There is nothing to catch up on: new payments and members go to Moneybird automatically.') }}</flux:text>
                        @endif
                    </div>

                    <div class="flex justify-between">
                        <flux:button type="button" variant="ghost" icon="arrow-left" wire:click="previousStep">{{ __('Back') }}</flux:button>
                        <flux:button type="submit" variant="primary" icon="check">{{ __('Finish setup') }}</flux:button>
                    </div>
                </form>
            @endif
        </div>
    @else
        {{-- Connected: status --}}
        <section class="sp-panel flex flex-col gap-4 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="sp-card-title">{{ __('Connected') }}</h2>
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
                @if ($this->canManageIntegrations)
                    <div class="flex flex-wrap justify-end gap-2">
                        <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="restartSetup">{{ __('Run setup again') }}</flux:button>
                        <flux:button size="sm" variant="ghost" icon="link-slash" wire:click="disconnect" wire:confirm="{{ __('Disconnect Moneybird? Existing invoices and contacts stay in Moneybird.') }}">{{ __('Disconnect') }}</flux:button>
                    </div>
                @endif
            </div>

            @if ($club->moneybird_last_error)
                <flux:callout icon="exclamation-triangle" variant="danger">
                    <flux:callout.heading>{{ __('The last Moneybird request failed') }}</flux:callout.heading>
                    <flux:callout.text class="break-all">{{ $club->moneybird_last_error }}</flux:callout.text>
                </flux:callout>
            @endif
        </section>

        @php($openCatchUpCount = $this->missingInvoiceCount + $this->unsyncedMemberCount)

        <flux:tab.group>
            <flux:tabs wire:model="tab">
                <flux:tab name="settings">{{ __('Settings') }}</flux:tab>
                <flux:tab name="catch-up">
                    {{ __('Catch up') }}
                    @if ($openCatchUpCount > 0)
                        <flux:badge size="sm" color="amber" class="ms-2">{{ $openCatchUpCount }}</flux:badge>
                    @endif
                </flux:tab>
            </flux:tabs>

            <flux:tab.panel name="settings">
                @unless ($this->canManageIntegrations)
                    <flux:callout icon="lock-closed" variant="secondary">
                        <flux:callout.text>{{ __('This is a demo club: SquadPass manages the Mollie and Moneybird settings.') }}</flux:callout.text>
                    </flux:callout>
                @else
                    <form wire:submit="saveSettings" class="flex flex-col gap-6">
                        <section class="sp-panel flex flex-col gap-6 p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="sp-card-title">{{ __('Invoices') }}</h2>
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
                        </section>

                        <section class="sp-panel flex flex-col gap-6 p-5">
                            <h2 class="sp-card-title">{{ __('Contacts') }}</h2>

                            <flux:switch wire:model="sync_contacts" :label="__('Sync members automatically')" :description="__('New members and changed names or email addresses are pushed to Moneybird as contacts right away.')" />
                        </section>

                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </div>
                    </form>
                @endunless
            </flux:tab.panel>

            <flux:tab.panel name="catch-up" class="flex flex-col gap-6">
                <section class="sp-panel flex flex-wrap items-center justify-between gap-4 p-5">
                    <div>
                        <h2 class="sp-card-title">{{ trans_choice(':count paid payment without invoice|:count paid payments without invoice', $this->missingInvoiceCount) }}</h2>
                        <flux:text class="mt-1">{{ __('Payments made before Moneybird was connected, or that failed earlier.') }}</flux:text>
                    </div>
                    <flux:button size="sm" icon="document-plus" wire:click="invoiceMissingPayments" :disabled="$this->missingInvoiceCount === 0">{{ __('Create invoices') }}</flux:button>
                </section>

                <section class="sp-panel flex flex-wrap items-center justify-between gap-4 p-5">
                    <div>
                        <h2 class="sp-card-title">{{ trans_choice(':count active member without Moneybird contact|:count active members without Moneybird contact', $this->unsyncedMemberCount) }}</h2>
                        <flux:text class="mt-1">{{ __('Push all active members to Moneybird; existing contacts are updated.') }}</flux:text>
                    </div>
                    <flux:button size="sm" icon="users" wire:click="syncAllContacts">{{ __('Sync all members') }}</flux:button>
                </section>
            </flux:tab.panel>
        </flux:tab.group>
    @endif
</div>
