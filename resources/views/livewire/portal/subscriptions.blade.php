<div class="flex w-full flex-col gap-8">
    @php($activeSubscriptions = $this->subscriptions->where('status', \App\Enums\SubscriptionStatus::Active))

    <x-portal.hero
        :eyebrow="__('Membership')"
        :title="__('My subscriptions')"
        :subtitle="__('Your running plans and everything your clubs offer.')"
    >
        @if ($this->subscriptions->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-3">
                <x-portal.stat :count-up="$activeSubscriptions->count()" :value="$activeSubscriptions->count()" :label="__('Active subscriptions')" :delay="260" />
                <x-portal.stat :count-up="$activeSubscriptions->contains(fn ($subscription) => $subscription->isUnlimited()) ? null : $activeSubscriptions->sum('credits_remaining')"
                    :value="$activeSubscriptions->contains(fn ($subscription) => $subscription->isUnlimited()) ? __('Unlimited') : $activeSubscriptions->sum('credits_remaining')"
                    :label="__('Credits left')"
                    :delay="320"
                />
                <x-portal.stat :count-up="$this->subscriptions->pluck('club_id')->unique()->count()" :value="$this->subscriptions->pluck('club_id')->unique()->count()" :label="__('Clubs')" :delay="380" />
            </div>
        @endif
    </x-portal.hero>

    @if ($this->subscriptions->isNotEmpty())
        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->subscriptions as $subscriptionIndex => $subscription)
                @php($active = $subscription->status === \App\Enums\SubscriptionStatus::Active)
                @php($planCredits = $subscription->plan->credits)
                @php($creditPercentage = $subscription->isUnlimited() || ! $planCredits ? 100 : min(100, (int) round(($subscription->credits_remaining ?? 0) / $planCredits * 100)))

                <div wire:key="sub-{{ $subscription->id }}"
                     class="stagger animate-rise group [perspective:1200px]"
                     style="--d: {{ min(600, $subscriptionIndex * 90) }}ms">
                    <div x-data="spTilt(5)"
                         @mousemove="track($event)"
                         @mouseleave="reset()"
                         :style="tiltStyle"
                         class="relative overflow-hidden rounded-2xl p-6 text-white transition-transform duration-200 ease-out will-change-transform {{ $active ? 'bg-blue-700' : 'bg-zinc-500 dark:bg-zinc-700' }}">

                        <div class="relative flex items-start justify-between">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-white/15 transition duration-300 group-hover:bg-white/25">
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

                        <div class="relative mt-8 flex items-end justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm {{ $active ? 'text-indigo-200' : 'text-zinc-200' }}">{{ $subscription->club->name }}</p>
                                <p class="sp-card-title mt-1">{{ $subscription->plan->name }}</p>
                                <p class="mt-2 text-sm {{ $active ? 'text-indigo-100' : 'text-zinc-100' }}">
                                    {{ $subscription->plan->formattedPrice() }} {{ strtolower($subscription->plan->interval->label()) }}
                                </p>
                            </div>

                            {{-- Credit ring --}}
                            <div class="relative size-18 shrink-0">
                                <svg class="size-18 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                                    <circle cx="18" cy="18" r="15.9155" fill="none" stroke="currentColor" stroke-width="2.5" class="text-white/20" />
                                    <circle cx="18" cy="18" r="15.9155" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                            class="sp-ring-value text-white" style="--sp-dash: {{ $creditPercentage }}" />
                                </svg>
                                <span class="absolute inset-0 flex flex-col items-center justify-center">
                                    @if ($subscription->isUnlimited())
                                        <flux:icon.bolt class="size-6" />
                                    @else
                                        <span class="text-lg leading-none font-bold tabular-nums" x-data="spCountUp({{ $subscription->credits_remaining ?? 0 }})" x-text="displayed">{{ $subscription->credits_remaining ?? 0 }}</span>
                                        <span class="text-[0.6rem] tracking-wide uppercase opacity-80">{{ __('credits') }}</span>
                                    @endif
                                </span>
                            </div>
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
                                             class="border-0 bg-white/15! ring-1 ring-white/25 transition [&_*]:text-white! hover:bg-white/25!">
                                    {{ __('Cancel') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($this->availablePlans->isNotEmpty())
        <div class="flex flex-col gap-4">
            <div class="stagger animate-slide-in flex items-center gap-3" style="--d: 120ms">
                <h2 class="text-sm font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Available subscriptions') }}</h2>
                <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800"></span>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->availablePlans as $planIndex => $plan)
                    <x-portal.card :delay="min(600, $planIndex * 80)" wire:key="plan-{{ $plan->id }}" class="flex flex-col gap-3 p-6">
                        <div class="relative">
                            <flux:text class="text-sm">{{ $plan->club->name }}</flux:text>
                            <flux:heading class="sp-card-title">{{ $plan->name }}</flux:heading>
                        </div>
                        @if ($plan->description)
                            <flux:text class="relative text-sm">{{ $plan->description }}</flux:text>
                        @endif
                        <flux:text class="relative text-sm">
                            {{ $plan->isUnlimited() ? __('Unlimited lessons') : trans_choice(':count lesson credit per period|:count lesson credits per period', $plan->credits) }}
                        </flux:text>
                        <div class="relative mt-auto flex items-center justify-between pt-2">
                            <div>
                                <flux:heading size="lg">{{ $plan->formattedPrice() }}</flux:heading>
                                <flux:text class="text-xs">{{ $plan->interval->label() }}</flux:text>
                            </div>
                            @if (isset($this->payableClubIds[$plan->club_id]))
                                <flux:button variant="primary" wire:click="subscribe({{ $plan->id }})">
                                    {{ __('Subscribe') }}
                                </flux:button>
                            @else
                                <flux:tooltip :content="__('This club has not set up online payments yet. Contact the club to take out a subscription.')">
                                    <flux:button variant="primary" disabled>{{ __('Subscribe') }}</flux:button>
                                </flux:tooltip>
                            @endif
                        </div>
                    </x-portal.card>
                @endforeach
            </div>
        </div>
    @elseif ($this->subscriptions->isEmpty())
        <x-portal.empty icon="credit-card" :heading="__('No subscriptions available')" :text="__('Your clubs do not offer subscriptions yet.')" />
    @endif
</div>
