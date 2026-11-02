{{-- display: contents makes the button a sibling of the other hero buttons, so they stay on one row and the error wraps below them --}}
<form wire:submit="start($event.target.querySelector('[name=cf-turnstile-response]')?.value ?? null)" class="contents">
    <button
        type="submit"
        wire:loading.attr="disabled"
        class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-zinc-200 ring-1 ring-white/15 transition hover:bg-white/5 hover:text-white disabled:cursor-wait disabled:opacity-70 sm:px-6 sm:text-base"
    >
        <span wire:loading.remove wire:target="start" class="inline-flex items-center gap-2">
            <flux:icon.play variant="micro" class="size-4" />
            {{ __('Try the demo') }}
        </span>
        <span wire:loading.flex wire:target="start" class="items-center gap-2">
            <flux:icon.loading variant="micro" class="size-4" />
            {{ __('Preparing your demo…') }}
        </span>
    </button>

    <div class="basis-full">
        <x-spam-protection livewire />
    </div>
</form>
