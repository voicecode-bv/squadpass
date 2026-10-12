{{-- Polls well inside the code's validity window so the screen is never showing an expired code. --}}
<div wire:poll.15s class="flex w-full max-w-2xl flex-col items-center gap-8">
    <div class="flex flex-col items-center gap-1 text-center">
        <p class="text-xs font-semibold tracking-wider text-blue-600 uppercase">{{ $club->name }}</p>
        <h1 class="text-3xl font-semibold tracking-tight text-zinc-900">{{ __('Check in with the app') }}</h1>
        <p class="mt-1 text-sm text-zinc-500">{{ __('Open SquadPass on your phone, tap Check in and scan this code.') }}</p>
    </div>

    <div class="sp-panel flex flex-col items-center gap-4 p-6 sm:p-8" x-data="spCheckInBanner({{ $club->id }})">
        {{--
            The mark sits over the middle of the code; the codes are encoded at
            error correction level H, which recovers the modules it covers.

            This box and the message that covers it are square on purpose: the
            code's finder patterns run right into the corners, so rounding the
            box would clip them and rounding the overlay would let them peek
            out around the curve.
        --}}
        <div class="relative w-64 bg-white sm:w-80 [&>svg]:h-auto [&>svg]:w-full">
            {!! $this->qrCodeSvg !!}

            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                <img
                    src="{{ asset('images/logos/squadpass-mark.png') }}"
                    alt=""
                    aria-hidden="true"
                    class="rounded-[22%] bg-white p-[1%]"
                    style="width: {{ $markWidth }}"
                />
            </div>

            {{-- A scan covers the code for a few seconds, whichever way it went:
                 the member gets an answer, and nobody scans a code twice. --}}
            <div
                x-cloak
                x-show="visible"
                class="animate-pop absolute inset-0 flex flex-col items-center justify-center gap-2 px-4 text-center"
                :class="refused ? 'bg-red-600' : 'bg-blue-600'"
            >
                <flux:icon.check-circle variant="solid" x-show="! refused" class="size-12 text-white" />
                <flux:icon.x-circle variant="solid" x-cloak x-show="refused" class="size-12 text-white" />

                <p class="text-xl font-semibold text-white" x-text="name"></p>
                <p class="text-sm text-white/80" x-text="detail"></p>
            </div>
        </div>
    </div>
</div>
