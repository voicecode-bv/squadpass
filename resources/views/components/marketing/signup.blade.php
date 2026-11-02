@props([
    'title' => __('Ready to get started?'),
    'text' => __('Create your club in minutes and invite your members today.'),
])

{{-- CTA: signup with account and club in one step --}}
<section id="signup" class="scroll-mt-8 pb-24">
    <div class="relative overflow-hidden rounded-3xl bg-linear-to-br from-blue-600 to-indigo-700 px-6 py-12 shadow-2xl shadow-blue-950/50 sm:px-12 lg:py-16">
        <div class="pointer-events-none absolute -top-24 left-1/2 h-64 w-[36rem] -translate-x-1/2 rounded-full bg-white/15 blur-3xl" aria-hidden="true"></div>
        @guest
            <div class="relative grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
                <div class="text-center lg:text-left">
                    <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $title }}</h2>
                    <p class="mt-4 text-lg text-blue-100">{{ $text }}</p>
                    <ul class="mt-8 hidden space-y-3 text-blue-50 lg:block">
                        @foreach ([
                            __(':days days free with every feature', ['days' => \App\Enums\ClubPlan::TRIAL_DAYS]),
                            __('No payment details needed'),
                            __('Cancel monthly'),
                        ] as $promise)
                            <li class="flex items-center gap-3">
                                <flux:icon.check-circle variant="micro" class="size-5 shrink-0 text-white" />
                                {{ $promise }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <livewire:clubs.signup />
            </div>
        @else
            <div class="relative text-center">
                <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $title }}</h2>
                <p class="mx-auto mt-4 max-w-xl text-lg text-blue-100">{{ $text }}</p>
                <a href="{{ route('dashboard') }}" class="mt-8 inline-block rounded-xl bg-white px-6 py-3 text-base font-semibold text-blue-700 shadow-lg transition hover:bg-blue-50">
                    {{ __('Dashboard') }}
                </a>
            </div>
        @endguest
    </div>
</section>
