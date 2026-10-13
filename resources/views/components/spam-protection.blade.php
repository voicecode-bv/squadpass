{{--
    Invisible bot checks for public signup forms; verified by App\Services\SpamProtection.
    Inside a Livewire component the honeypot binds to `website` and the start time
    lives server-side, so only plain HTML forms need the hidden timestamp field.
--}}
@props([
    'livewire' => false,
])

@php
    $spamProtection = app(\App\Services\SpamProtection::class);
@endphp

<div class="absolute -left-[9999px] size-px overflow-hidden" aria-hidden="true">
    <label>
        Website
        <input
            type="text"
            name="{{ \App\Services\SpamProtection::HONEYPOT_FIELD }}"
            tabindex="-1"
            autocomplete="off"
            @if ($livewire) wire:model="website" @endif
        />
    </label>
</div>

@unless ($livewire)
    <input type="hidden" name="{{ \App\Services\SpamProtection::STARTED_AT_FIELD }}" value="{{ $spamProtection->startedAtToken() }}" />
@endunless

@if ($spamProtection->turnstileEnabled())
    <div
        wire:ignore
        x-data
        x-on:turnstile-reset.window="window.turnstile?.reset()"
    >
        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="auto"></div>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    </div>
@endif

<flux:error :name="\App\Services\SpamProtection::ERROR_KEY" />
