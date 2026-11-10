{{-- How it works: three steps from sign-up to members, shared by the segment and feature pages. --}}
<section class="py-16 lg:py-24">
    <div class="mx-auto max-w-2xl text-center">
        <h2 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-4xl">{{ __('Up and running today') }}</h2>
    </div>

    <ol class="mt-14 grid gap-6 lg:grid-cols-3">
        @foreach ([
            ['title' => __('Create your club'), 'text' => __('Account and club in one step, without payment details.')],
            ['title' => __('Set it up with the wizard'), 'text' => __('Prepare your schedule, prices and staff, or import your existing member list.')],
            ['title' => __('Invite your members'), 'text' => __('Members book, pay and check in via the portal or your own app.')],
        ] as $step)
            <li class="rounded-2xl bg-white dark:bg-white/5 p-6 ring-1 ring-zinc-200 dark:ring-white/10">
                <span class="flex size-10 items-center justify-center rounded-full bg-blue-600 font-semibold text-white">{{ $loop->iteration }}</span>
                <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">{{ $step['title'] }}</h3>
                <p class="mt-2 leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $step['text'] }}</p>
            </li>
        @endforeach
    </ol>

    @guest
        <div class="mt-10 text-center">
            <a href="#signup" class="inline-block rounded-xl bg-linear-to-r from-blue-500 to-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/40 transition hover:from-blue-400 hover:to-indigo-500">
                {{ __('Start your free trial') }}
            </a>
        </div>
    @endguest
</section>
