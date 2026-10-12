<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Club settings')" :subtitle="$club->name" />

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="name" :label="__('Club name')" required />

        <flux:field>
            <flux:label>{{ __('Club photo') }}</flux:label>

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

            <flux:description>{{ __('Shown on the club card under My clubs. JPG or PNG, up to 4 MB.') }}</flux:description>
            <flux:error name="photo" />
        </flux:field>

        @if ($club->hasAddon(\App\Enums\AddonKey::Mollie))
            <flux:field>
                <flux:input
                    wire:model="mollie_api_key"
                    :label="__('Mollie API key')"
                    type="password"
                    viewable
                    placeholder="test_..."
                />
                <flux:description>
                    @if (str_starts_with($mollie_api_key, 'live_'))
                        {{ __('Live mode: real payments will be processed.') }}
                    @elseif (str_starts_with($mollie_api_key, 'test_'))
                        {{ __('Test mode: no real payments will be processed.') }}
                    @else
                        {{ __('Members can only pay online once a Mollie API key is set.') }}
                    @endif
                </flux:description>
            </flux:field>
        @else
            <flux:callout icon="credit-card" variant="secondary">
                <flux:callout.heading>{{ __('Online payments (Mollie)') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Activate the Mollie add-on to let members pay online for strip cards and subscriptions.') }}</flux:callout.text>
                <x-slot name="actions">
                    <flux:button :href="route('clubs.addons', $club)" wire:navigate size="sm">{{ __('View add-ons') }}</flux:button>
                </x-slot>
            </flux:callout>
        @endif

        <flux:separator />

        @if ($club->hasAddon(\App\Enums\AddonKey::Moneybird))
            <flux:callout icon="document-text" variant="secondary">
                <flux:callout.heading>{{ __('Moneybird') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ $club->hasMoneybirdConfigured() ? __('Connected to administration :id.', ['id' => $club->moneybird_administration_id]) : __('Not connected yet.') }}
                    {{ __('Invoice settings and contact sync are managed on the Moneybird page.') }}
                </flux:callout.text>
                <x-slot name="actions">
                    <flux:button :href="route('clubs.moneybird', $club)" wire:navigate size="sm">{{ __('Moneybird settings') }}</flux:button>
                </x-slot>
            </flux:callout>
        @else
            <flux:callout icon="document-text" variant="secondary">
                <flux:callout.heading>{{ __('Invoicing (Moneybird)') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Activate the Moneybird add-on to sync members as contacts and invoice paid payments automatically.') }}</flux:callout.text>
                <x-slot name="actions">
                    <flux:button :href="route('clubs.addons', $club)" wire:navigate size="sm">{{ __('View add-ons') }}</flux:button>
                </x-slot>
            </flux:callout>
        @endif

        <flux:separator />

        <flux:switch wire:model="self_signup" :label="__('Open for online registration')" :description="__('Anyone with the join link below can become a member directly.')" />

        @if ($self_signup)
            <flux:field>
                <flux:input :value="route('clubs.join', $club)" :label="__('Join link')" readonly copyable />
                <flux:description>{{ __('Share this link (or a QR code of it) so people can become a member themselves.') }}</flux:description>
            </flux:field>
        @endif

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
        </div>
    </form>
</div>
