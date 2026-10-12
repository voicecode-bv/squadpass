@php($impersonator = \App\Services\Impersonation\Impersonation::impersonator())

@if ($impersonator)
    <div class="fixed inset-x-0 bottom-0 z-50 flex justify-center p-4">
        <div class="flex max-w-full items-center gap-3 rounded-full border border-amber-300 bg-amber-50 py-2 ps-4 pe-2 shadow-lg dark:border-amber-500/40 dark:bg-amber-950">
            <flux:icon.eye variant="micro" class="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
            <p class="truncate text-sm text-amber-900 dark:text-amber-100">
                {{ __('You are viewing the app as :name.', ['name' => auth()->user()->name]) }}
            </p>
            <form method="POST" action="{{ route('impersonate.stop') }}">
                @csrf
                <flux:button type="submit" size="sm" variant="filled" icon="x-mark" class="shrink-0">
                    {{ __('Stop') }}
                </flux:button>
            </form>
        </div>
    </div>
@endif
