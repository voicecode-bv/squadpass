<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Club settings')" :subtitle="$club->name" />

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:tab.group>
            <flux:tabs wire:model="tab">
                <flux:tab name="general">{{ __('General') }}</flux:tab>
                <flux:tab name="registration">{{ __('Registration') }}</flux:tab>
                <flux:tab name="lessons">{{ __('Lessons & credits') }}</flux:tab>
                {{-- Integrations link to owner-only pages. --}}
                @can('update', $club)
                <flux:tab name="integrations">{{ __('Integrations') }}</flux:tab>
                @endcan
            </flux:tabs>

            <flux:tab.panel name="general" class="flex flex-col gap-6">
                <section class="sp-panel flex flex-col gap-6 p-5">
                    <h2 class="sp-card-title">{{ __('Club') }}</h2>

                    <flux:input wire:model="name" :label="__('Club name')" required />

                    <flux:field>
                        <flux:label>{{ __('Club photo') }}</flux:label>
                        <flux:description>{{ __('Shown on the club card under My clubs. JPG or PNG, up to 4 MB.') }}</flux:description>

                        {{-- A picked file only previews when it is an image; anything else falls back to the stored photo. --}}
                        @php($photoPreview = $photo?->isPreviewable() ? $photo->temporaryUrl() : $club->photoUrl())

                        <div class="flex flex-wrap items-center gap-4">
                            @if ($photoPreview)
                                <img src="{{ $photoPreview }}" alt="{{ $club->name }}"
                                     class="h-24 w-40 rounded-xl object-cover" />
                            @else
                                <div class="flex h-24 w-40 items-center justify-center rounded-xl bg-zinc-100 text-sm text-zinc-400 dark:bg-zinc-800">
                                    {{ __('No photo yet') }}
                                </div>
                            @endif

                            <div class="flex flex-col gap-2">
                                <flux:input type="file" wire:model="photo" accept="image/*" :label="__('Upload a photo')" label:sr-only />

                                @if ($club->photoUrl())
                                    <flux:button type="button" variant="ghost" size="sm" wire:click="deletePhoto">{{ __('Remove photo') }}</flux:button>
                                @endif
                            </div>
                        </div>

                        <flux:error name="photo" />
                    </flux:field>
                </section>

                @can('manage-platform')
                    <section class="sp-panel flex flex-col gap-6 p-5">
                        <h2 class="sp-card-title">{{ __('Platform') }}</h2>

                        <flux:switch wire:model="is_demo" :label="__('Demo club')" :description="__('Unlocks every add-on without a subscription. Only superadmins can change the Mollie and Moneybird settings of a demo club.')" />
                    </section>
                @endcan
            </flux:tab.panel>

            <flux:tab.panel name="registration">
                <section class="sp-panel flex flex-col gap-6 p-5">
                    <h2 class="sp-card-title">{{ __('Online registration') }}</h2>

                    <flux:switch wire:model.live="self_signup" :label="__('Open for online registration')" :description="__('Anyone with the join link below can become a member directly.')" />

                    @if ($self_signup)
                        <flux:input
                            :value="route('clubs.join', $club)"
                            :label="__('Join link')"
                            :description="__('Share this link (or a QR code of it) so people can become a member themselves.')"
                            readonly
                            copyable
                        />

                        <div>
                            <flux:button type="button" size="sm" icon="qr-code" wire:click="downloadJoinQrCode">
                                {{ __('Download QR code') }}
                            </flux:button>
                        </div>
                    @endif
                </section>
            </flux:tab.panel>

            <flux:tab.panel name="lessons">
                <section class="sp-panel flex flex-col gap-6 p-5">
                    <h2 class="sp-card-title">{{ __('Cancellations and credits') }}</h2>

                    <flux:input
                        type="number"
                        min="0"
                        max="168"
                        wire:model="cancellation_deadline_hours"
                        :label="__('Cancellation deadline (hours before the lesson)')"
                        :description="__('Members who cancel at least this long before a lesson get their credit back. Later cancellations still free up the spot, but the credit stays spent. Use 0 to always refund.')"
                        required
                    />

                    <flux:switch wire:model.live="credits_expire" :label="__('Credits expire')" :description="__('Expired credits are removed from the balance. A change applies to credits added from now on.')" />

                    @if ($credits_expire)
                        <flux:input
                            type="number"
                            min="1"
                            max="730"
                            wire:model="credit_expiry_days"
                            :label="__('Credits expire after (days)')"
                            :description="__('Counted from the moment credits are added for a new billing period.')"
                            required
                        />
                    @endif
                </section>
            </flux:tab.panel>

            @can('update', $club)
            <flux:tab.panel name="integrations" class="flex flex-col gap-6">
                @if ($club->hasAddon(\App\Enums\AddonKey::Mollie))
                    <section class="sp-panel flex flex-wrap items-center justify-between gap-4 p-5">
                        <div>
                            <h2 class="sp-card-title">{{ __('Mollie') }}</h2>
                            <flux:text class="mt-1">
                                @if ($club->mollieApiKey())
                                    {{ $club->mollieIsLive() ? __('Connected in live mode.') : __('Connected in test mode.') }}
                                @else
                                    {{ __('Not connected yet.') }}
                                @endif
                                {{ __('The API key and payment overview are on the Mollie page.') }}
                            </flux:text>
                        </div>

                        <flux:button :href="route('clubs.mollie', $club)" wire:navigate size="sm">{{ __('Mollie settings') }}</flux:button>
                    </section>
                @else
                    <livewire:clubs.addon-cta :club="$club" :addon-key="\App\Enums\AddonKey::Mollie" wire:key="addon-cta-Mollie" />
                @endif

                @if ($club->hasAddon(\App\Enums\AddonKey::Moneybird))
                    <section class="sp-panel flex flex-wrap items-center justify-between gap-4 p-5">
                        <div>
                            <h2 class="sp-card-title">{{ __('Moneybird') }}</h2>
                            <flux:text class="mt-1">
                                {{ $club->hasMoneybirdConfigured() ? __('Connected to administration :id.', ['id' => $club->moneybird_administration_id]) : __('Not connected yet.') }}
                                {{ __('Invoice settings and contact sync are managed on the Moneybird page.') }}
                            </flux:text>
                        </div>

                        <flux:button :href="route('clubs.moneybird', $club)" wire:navigate size="sm">{{ __('Moneybird settings') }}</flux:button>
                    </section>
                @else
                    <livewire:clubs.addon-cta :club="$club" :addon-key="\App\Enums\AddonKey::Moneybird" wire:key="addon-cta-Moneybird" />
                @endif
            </flux:tab.panel>
            @endcan
        </flux:tab.group>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
        </div>
    </form>
</div>
