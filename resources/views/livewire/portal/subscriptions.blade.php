<div class="flex w-full flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('My subscriptions') }}</flux:heading>
    </div>

    @if ($this->subscriptions->isNotEmpty())
        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->subscriptions as $subscription)
                @php($active = $subscription->status === \App\Enums\SubscriptionStatus::Active)
                <div wire:key="sub-{{ $subscription->id }}"
                     class="relative overflow-hidden rounded-3xl p-6 text-white ring-1 ring-white/20 shadow-xl {{ $active ? 'bg-linear-to-br from-indigo-500 via-indigo-600 to-blue-700 shadow-indigo-500/25' : 'bg-linear-to-br from-zinc-400 via-zinc-500 to-zinc-600 shadow-zinc-500/20 dark:from-zinc-600 dark:via-zinc-700 dark:to-zinc-800' }}">
                    <div class="pointer-events-none absolute -top-16 -right-10 size-48 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>

                    <div class="relative flex items-start justify-between">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-white/15">
                            <x-app-logo-icon class="size-6 fill-current text-white" />
                        </span>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold tracking-widest uppercase {{ match($subscription->status) {
                            \App\Enums\SubscriptionStatus::Active => 'bg-white/15',
                            \App\Enums\SubscriptionStatus::Pending => 'bg-yellow-400/30',
                            \App\Enums\SubscriptionStatus::PastDue => 'bg-red-500/40',
                            default => 'bg-white/15',
                        } }}">
                            {{ $subscription->status->label() }}
                        </span>
                    </div>

                    <div class="relative mt-8">
                        <p class="text-sm {{ $active ? 'text-indigo-200' : 'text-zinc-200' }}">{{ $subscription->club->name }}</p>
                        <p class="mt-1 text-2xl font-semibold">{{ $subscription->plan->name }}</p>
                        <p class="mt-2 text-sm {{ $active ? 'text-indigo-100' : 'text-zinc-100' }}">
                            {{ $subscription->plan->formattedPrice() }} {{ strtolower($subscription->plan->interval->label()) }}
                            · {{ $subscription->isUnlimited() ? __('unlimited') : trans_choice(':count credit left|:count credits left', $subscription->credits_remaining ?? 0) }}
                        </p>
                    </div>

                    <div class="relative mt-6 flex items-end justify-between gap-4">
                        <p class="text-xs {{ $active ? 'text-indigo-200' : 'text-zinc-200' }}">
                            @if ($subscription->next_billing_at)
                                {{ __('next billing :date', ['date' => $subscription->next_billing_at->format('d-m-Y')]) }}
                            @elseif ($subscription->ends_at)
                                {{ __('access until :date', ['date' => $subscription->ends_at->format('d-m-Y')]) }}
                            @endif
                        </p>
                        @if (in_array($subscription->status, [\App\Enums\SubscriptionStatus::Active, \App\Enums\SubscriptionStatus::PastDue], true))
                            <flux:button size="sm" wire:click="cancel({{ $subscription->id }})"
                                         class="border-0 bg-white/10 text-white ring-1 ring-white/20 hover:bg-white/20 hover:text-white">
                                {{ __('Cancel') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($this->availablePlans->isNotEmpty())
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Available subscriptions') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->availablePlans as $plan)
                    <div wire:key="plan-{{ $plan->id }}"
                         class="group flex flex-col gap-3 rounded-2xl bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-blue-500/10 dark:bg-zinc-900">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition group-hover:bg-linear-to-br group-hover:from-blue-500 group-hover:to-indigo-600 group-hover:text-white dark:bg-blue-500/10 dark:text-blue-400">
                            <flux:icon.credit-card class="size-5" />
                        </span>
                        <div>
                            <flux:text class="text-sm">{{ $plan->club->name }}</flux:text>
                            <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                        </div>
                        @if ($plan->description)
                            <flux:text class="text-sm">{{ $plan->description }}</flux:text>
                        @endif
                        <flux:text class="text-sm">
                            {{ $plan->isUnlimited() ? __('Unlimited lessons') : trans_choice(':count lesson credit per period|:count lesson credits per period', $plan->credits) }}
                        </flux:text>
                        <div class="mt-auto flex items-center justify-between">
                            <div>
                                <flux:heading size="lg">{{ $plan->formattedPrice() }}</flux:heading>
                                <flux:text class="text-xs">{{ $plan->interval->label() }}</flux:text>
                            </div>
                            <flux:button variant="primary" wire:click="subscribe({{ $plan->id }})" class="border-0 bg-linear-to-r from-blue-500 to-indigo-600 hover:from-blue-400 hover:to-indigo-500">
                                {{ __('Subscribe') }}
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif ($this->subscriptions->isEmpty())
        <flux:callout icon="credit-card">
            <flux:callout.heading>{{ __('No subscriptions available') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Your clubs do not offer subscriptions yet.') }}</flux:callout.text>
        </flux:callout>
    @endif
</div>
