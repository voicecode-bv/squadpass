<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Mollie')" :subtitle="$club->name">
        <x-slot:actions>
            <img src="{{ asset(\App\Enums\AddonKey::Mollie->logoPath()) }}" alt="Mollie" class="h-6 w-auto invert dark:invert-0" />
        </x-slot:actions>
    </x-portal.hero>

    @if (! $this->hasAddon)
        <livewire:clubs.addon-cta :club="$club" :addon-key="\App\Enums\AddonKey::Mollie" wire:key="addon-cta-Mollie" />
    @else
        {{-- Status --}}
        <section class="sp-panel flex flex-col gap-4 p-5">
            <div>
                @if ($club->mollieApiKey())
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="sp-card-title">{{ __('Connected') }}</h2>
                        @if ($club->mollieIsLive())
                            <flux:badge size="sm" color="green">{{ __('Live') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="amber">{{ __('Test mode') }}</flux:badge>
                        @endif
                    </div>
                    <flux:text class="mt-1">
                        @if ($club->mollie_profile_name)
                            {{ __('Payments go to the Mollie profile :name.', ['name' => $club->mollie_profile_name]) }}
                        @endif
                        {{ $club->mollieIsLive() ? __('Members pay for real.') : __('No real payments are processed.') }}
                    </flux:text>
                @else
                    <h2 class="sp-card-title">{{ __('Not connected yet.') }}</h2>
                    <flux:text class="mt-1">{{ __('Members can only pay online once a Mollie API key is set.') }}</flux:text>
                @endif
            </div>

            @if ($club->mollie_last_error)
                <flux:callout icon="exclamation-triangle" variant="danger">
                    <flux:callout.heading>{{ __('The last Mollie request failed') }}</flux:callout.heading>
                    <flux:callout.text class="break-all">{{ $club->mollie_last_error }}</flux:callout.text>
                </flux:callout>
            @endif
        </section>

        <flux:tab.group>
            <flux:tabs wire:model="tab">
                <flux:tab name="connection">{{ __('Connection') }}</flux:tab>
                <flux:tab name="payments">
                    {{ __('Payments') }}
                    @if ($this->failedChargeCount > 0)
                        <flux:badge size="sm" color="red" class="ms-2">{{ $this->failedChargeCount }}</flux:badge>
                    @endif
                </flux:tab>
            </flux:tabs>

            <flux:tab.panel name="connection">
                @if (! $this->canManageIntegrations)
                    <flux:callout icon="lock-closed" variant="secondary">
                        <flux:callout.text>
                            {{ $club->is_demo ? __('This is a demo club: SquadPass manages the Mollie and Moneybird settings.') : __('Only the club owner can change the Mollie connection.') }}
                        </flux:callout.text>
                    </flux:callout>
                @else
                    <form wire:submit="save" class="sp-panel flex flex-col gap-6 p-5">
                        <div>
                            <h2 class="sp-card-title">{{ __('API key') }}</h2>
                            <flux:text class="mt-1">
                                {{ __('Find your key in the Mollie dashboard under Developers, API keys. We check it with Mollie before saving.') }}
                            </flux:text>
                        </div>

                        <flux:input
                            wire:model="mollie_api_key"
                            :label="__('Mollie API key')"
                            :description="__('Use a test_ key to try things out, and a live_ key to receive real payments.')"
                            type="password"
                            viewable
                            placeholder="test_..."
                        />

                        <div class="flex flex-wrap justify-end gap-2">
                            @if ($club->mollieApiKey())
                                <flux:button type="button" variant="ghost" icon="link-slash" wire:click="disconnect" wire:confirm="{{ __('Disconnect Mollie? Members can no longer pay online until a new key is set.') }}">
                                    {{ __('Disconnect') }}
                                </flux:button>
                            @endif
                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </div>
                    </form>
                @endif
            </flux:tab.panel>

            <flux:tab.panel name="payments" class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <flux:text>{{ __('Payments by members of this club, newest first.') }}</flux:text>

                    <flux:select wire:model.live="status" size="sm" class="max-w-52" :aria-label="__('Status')">
                        <flux:select.option value="">{{ __('All payments') }}</flux:select.option>
                        <flux:select.option value="paid">{{ __('Paid') }}</flux:select.option>
                        <flux:select.option value="open">{{ __('Open') }}</flux:select.option>
                        <flux:select.option value="failed">{{ __('Failed') }}</flux:select.option>
                        <flux:select.option value="refunded">{{ __('Refunded') }}</flux:select.option>
                    </flux:select>
                </div>

                @if ($this->payments->isEmpty())
                    <flux:text>{{ __('No payments.') }}</flux:text>
                @else
                    <x-table-card>
                        <flux:table :paginate="$this->payments">
                            <flux:table.columns>
                                <flux:table.column>{{ __('Date') }}</flux:table.column>
                                <flux:table.column>{{ __('Member') }}</flux:table.column>
                                <flux:table.column>{{ __('Description') }}</flux:table.column>
                                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                                <flux:table.column>{{ __('Status') }}</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($this->payments as $payment)
                                    <flux:table.row :key="'payment-'.$payment->id">
                                        <flux:table.cell class="sp-strong">{{ ($payment->paid_at ?? $payment->created_at)->timezone($club->timezone)->isoFormat('D MMM YYYY, HH:mm') }}</flux:table.cell>
                                        <flux:table.cell>
                                            @if ($this->membershipIdsByUser[$payment->user_id] ?? null)
                                                <flux:link :href="route('clubs.members.show', [$club, $this->membershipIdsByUser[$payment->user_id]])" wire:navigate variant="ghost">{{ $payment->user->name }}</flux:link>
                                            @else
                                                {{ $payment->user->name }}
                                            @endif
                                        </flux:table.cell>
                                        <flux:table.cell>{{ $payment->purposeLabel() }}</flux:table.cell>
                                        <flux:table.cell>&euro; {{ number_format($payment->amount_cents / 100, 2, ',', '.') }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge size="sm" :color="match($payment->status) {
                                                \App\Enums\PaymentStatus::Paid => 'green',
                                                \App\Enums\PaymentStatus::Open => 'yellow',
                                                \App\Enums\PaymentStatus::Refunded => 'zinc',
                                                default => 'red',
                                            }">{{ $payment->status->label() }}</flux:badge>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </x-table-card>
                @endif
            </flux:tab.panel>
        </flux:tab.group>
    @endif
</div>
