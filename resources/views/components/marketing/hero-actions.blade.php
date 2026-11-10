{{-- Hero call to action plus the three promises, shared by the segment and feature pages. --}}
@php($days = \App\Enums\ClubPlan::TRIAL_DAYS)

<div class="flex flex-wrap items-center gap-3 sm:gap-4">
    @guest
        <a href="#signup" class="shrink-0 rounded-xl bg-linear-to-r from-blue-500 to-indigo-600 px-4 py-3 text-sm font-semibold sm:px-6 sm:text-base text-white shadow-lg shadow-blue-500/40 transition hover:from-blue-400 hover:to-indigo-500">
            {{ __('Start your free trial') }}
        </a>
        <livewire:clubs.start-demo />
    @else
        <a href="#pricing" class="rounded-xl px-6 py-3 text-base font-semibold text-zinc-700 dark:text-zinc-300 ring-1 ring-zinc-300 dark:ring-white/15 transition hover:bg-zinc-100 dark:hover:bg-white/5 hover:text-zinc-900 dark:hover:text-white">
            {{ __('See pricing') }}
        </a>
    @endguest
</div>

<ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-zinc-600 dark:text-zinc-400">
    @foreach ([__(':days days free', ['days' => $days]), __('No payment details needed'), __('Cancel monthly')] as $promise)
        <li class="flex items-center gap-2">
            <flux:icon.check variant="micro" class="size-4 text-blue-600 dark:text-blue-300" />
            {{ $promise }}
        </li>
    @endforeach
</ul>
