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

                    <flux:field>
                        <flux:label>{{ __('Logo for light mode') }}</flux:label>
                        <flux:description>{{ __('Shown above the navigation in light mode and at the top of every email your members receive. Use a PNG or JPG of 112 pixels high and at most 480 pixels wide, with a transparent or white background, up to 2 MB.') }}</flux:description>

                        @php($logoPreview = $logo?->isPreviewable() ? $logo->temporaryUrl() : $club->logoUrl())

                        <div class="flex flex-wrap items-center gap-4">
                            @if ($logoPreview)
                                <div class="flex h-24 w-40 items-center justify-center rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700">
                                    <img src="{{ $logoPreview }}" alt="{{ $club->name }}" class="max-h-full max-w-full object-contain" />
                                </div>
                            @else
                                <div class="flex h-24 w-40 items-center justify-center rounded-xl bg-zinc-100 text-sm text-zinc-400 dark:bg-zinc-800">
                                    {{ __('No logo yet') }}
                                </div>
                            @endif

                            <div class="flex flex-col gap-2">
                                <flux:input type="file" wire:model="logo" accept="image/png,image/jpeg,image/gif" :label="__('Upload a logo')" label:sr-only />

                                @if ($club->logo_path)
                                    <flux:button type="button" variant="ghost" size="sm" wire:click="deleteLogo">{{ __('Remove logo') }}</flux:button>
                                @endif
                            </div>
                        </div>

                        <flux:error name="logo" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Logo for dark mode') }}</flux:label>
                        <flux:description>{{ __('Shown above the navigation in dark mode; without one, the light logo is used. Use a PNG of 112 pixels high and at most 480 pixels wide, with a transparent background and light colours, up to 2 MB.') }}</flux:description>

                        @php($darkLogoPreview = $darkLogo?->isPreviewable() ? $darkLogo->temporaryUrl() : ($club->logo_dark_path ? $club->darkLogoUrl() : null))

                        <div class="flex flex-wrap items-center gap-4">
                            @if ($darkLogoPreview)
                                <div class="flex h-24 w-40 items-center justify-center rounded-xl border border-zinc-700 bg-zinc-900 p-3">
                                    <img src="{{ $darkLogoPreview }}" alt="{{ $club->name }}" class="max-h-full max-w-full object-contain" />
                                </div>
                            @else
                                <div class="flex h-24 w-40 items-center justify-center rounded-xl bg-zinc-100 px-3 text-center text-sm text-zinc-400 dark:bg-zinc-800">
                                    {{ __('Uses the light logo') }}
                                </div>
                            @endif

                            <div class="flex flex-col gap-2">
                                <flux:input type="file" wire:model="darkLogo" accept="image/png,image/jpeg,image/gif" :label="__('Upload a logo')" label:sr-only />

                                @if ($club->logo_dark_path)
                                    <flux:button type="button" variant="ghost" size="sm" wire:click="deleteDarkLogo">{{ __('Remove logo') }}</flux:button>
                                @endif
                            </div>
                        </div>

                        <flux:error name="darkLogo" />
                    </flux:field>
                </section>

                <section class="sp-panel flex flex-col gap-6 p-5">
                    <h2 class="sp-card-title">{{ __('Brand colour') }}</h2>

                    <flux:switch wire:model.live="custom_brand_color" :label="__('Use our own brand colour')" :description="__('Replaces SquadPass blue in the app, the member portal, the public sign-up and trial pages, and the emails of your club. Light colours are darkened where white text sits on them, so everything stays readable.')" />

                    @if ($custom_brand_color)
                        <div class="flex flex-wrap items-end gap-6">
                            <flux:input type="color" wire:model.live.debounce.250ms="brand_color" :label="__('Colour')" class="w-24" />

                            <div class="flex flex-col gap-2">
                                <span class="sp-card-subtitle">{{ __('Preview') }}</span>
                                <div class="flex items-center gap-3">
                                    <div class="flex overflow-hidden rounded-lg">
                                        @foreach ([100, 300, 500, 600, 700, 900] as $shade)
                                            <span class="size-8" style="background-color: {{ $this->brandPreview[$shade] }}"></span>
                                        @endforeach
                                    </div>
                                    <span class="rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background-color: {{ $this->brandPreview[600] }}">{{ __('Button') }}</span>
                                </div>
                            </div>
                        </div>

                        <flux:error name="brand_color" />
                    @endif
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

                <section class="sp-panel mt-6 flex flex-col gap-6 p-5">
                    <h2 class="sp-card-title">{{ __('Trial lessons') }}</h2>

                    <flux:switch wire:model.live="trial_lessons_enabled" :label="__('Offer trial lessons')" :description="__('Visitors book a trial lesson without an account. Choose per lesson whether it is offered and what it costs.')" />

                    @if ($trial_lessons_enabled)
                        <flux:input
                            :value="route('clubs.trial', $club)"
                            :label="__('Trial lesson link')"
                            :description="__('Share this link on your website or social media.')"
                            readonly
                            copyable
                        />
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

                <section class="sp-panel mt-6 flex flex-col gap-6 p-5">
                    <h2 class="sp-card-title">{{ __('Lesson reminders') }}</h2>

                    <flux:switch wire:model.live="lesson_reminders" :label="__('Email members before their lessons')" :description="__('Everyone booked for a lesson gets a reminder email. Members can turn this off in their profile.')" />

                    @if ($lesson_reminders)
                        <flux:input
                            type="number"
                            min="1"
                            max="72"
                            wire:model="lesson_reminder_hours"
                            :label="__('Send the reminder (hours before the lesson)')"
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
