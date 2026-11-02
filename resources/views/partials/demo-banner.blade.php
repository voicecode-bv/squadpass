@if (auth()->user()?->isDemoSandbox())
    <div class="fixed inset-x-0 bottom-0 z-50 flex justify-center p-4">
        <div class="flex max-w-full flex-wrap items-center justify-center gap-3 rounded-2xl bg-blue-600 py-2 ps-4 pe-2 text-white shadow-lg sm:rounded-full">
            <flux:icon.play variant="micro" class="size-4 shrink-0" />
            <p class="text-sm">
                {{ __('You are exploring a demo club. Everything is deleted :time.', ['time' => auth()->user()->demo_expires_at->diffForHumans()]) }}
            </p>
            <form method="POST" action="{{ route('demo.leave') }}">
                @csrf
                <button type="submit" class="rounded-full bg-white px-4 py-1.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-50">
                    {{ __('Create your own club') }}
                </button>
            </form>
        </div>
    </div>
@endif
