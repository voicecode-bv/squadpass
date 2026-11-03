@props([
    'transactions',
    'memberId',
    'hasMore' => false,
    'showMoreAction' => null,
    'forMember' => false,
])

{{-- Credit history as a vertical timeline; staff see who acted, members see "you" or "the club". Expects club, lesson, occurrence and actor loaded. --}}
@if ($transactions->isEmpty())
    <flux:text>{{ __('No credit activity yet.') }}</flux:text>
@else
    <ol class="relative flex flex-col gap-5 border-s border-zinc-200 ps-6 dark:border-zinc-700">
        @foreach ($transactions as $transaction)
            @php($timezone = $transaction->club->timezone)

            <li wire:key="credit-{{ $transaction->id }}" class="relative">
                <span @class([
                    'absolute -start-[1.85rem] top-1.5 size-3 rounded-full ring-4 ring-white dark:ring-zinc-900',
                    'bg-green-500' => $transaction->type->color() === 'green',
                    'bg-blue-600' => $transaction->type->color() === 'blue',
                    'bg-amber-500' => $transaction->type->color() === 'amber',
                    'bg-zinc-400' => $transaction->type->color() === 'zinc',
                ])></span>

                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <p class="sp-strong text-sm">
                        {{ $transaction->type->label() }}
                        @if ($transaction->amount !== 0)
                            <span class="tabular-nums">{{ $transaction->amount > 0 ? '+'.$transaction->amount : $transaction->amount }}</span>
                        @elseif ($transaction->type === \App\Enums\CreditTransactionType::Forfeited)
                            <span class="sp-muted font-normal">· {{ __('credit not refunded') }}</span>
                        @else
                            <span class="sp-muted font-normal">· {{ __('refund expired') }}</span>
                        @endif
                    </p>
                    <p class="sp-muted text-xs tabular-nums">
                        {{ $transaction->created_at->timezone($timezone)->isoFormat('D MMM YYYY HH:mm') }}
                    </p>
                </div>

                <p class="sp-muted mt-1 text-xs">
                    @if ($forMember)
                        {{ $transaction->club->name }} ·
                    @endif
                    @if ($transaction->lesson)
                        {{ $transaction->lesson->name }}@if ($transaction->occurrence) · {{ $transaction->occurrence->starts_at->timezone($timezone)->isoFormat('dd D MMM HH:mm') }}@endif ·
                    @endif
                    {{ __('Balance :balance', ['balance' => $transaction->balance_after]) }}
                    ·
                    @if ($transaction->actor_id === null)
                        {{ __('Automatic') }}
                    @elseif ($transaction->actor_id === $memberId)
                        {{ $forMember ? __('By you') : __('By the member') }}
                    @elseif ($forMember)
                        {{ __('By the club') }}
                    @else
                        {{ __('By :name', ['name' => $transaction->actor?->name ?? __('a former staff member')]) }}
                    @endif
                </p>
            </li>
        @endforeach
    </ol>

    @if ($hasMore && $showMoreAction)
        <div>
            <flux:button size="sm" variant="ghost" wire:click="{{ $showMoreAction }}">{{ __('Show more') }}</flux:button>
        </div>
    @endif
@endif
