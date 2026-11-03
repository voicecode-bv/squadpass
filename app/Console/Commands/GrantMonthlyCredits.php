<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\Credits\CreditLedger;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class GrantMonthlyCredits extends Command
{
    protected $signature = 'credits:grant';

    protected $description = 'Add the monthly lesson credits of every running subscription, whatever its billing interval';

    public function handle(CreditLedger $ledger): int
    {
        $granted = 0;
        $failed = 0;

        Subscription::query()
            ->whereDate('next_credits_at', '<=', today())
            ->where(fn (Builder $query) => $query
                ->where('status', SubscriptionStatus::Active)
                ->orWhere(fn (Builder $cancelled) => $cancelled
                    ->where('status', SubscriptionStatus::Cancelled)
                    ->whereDate('ends_at', '>=', today())))
            ->with(['plan', 'club'])
            ->chunkById(200, function ($subscriptions) use ($ledger, &$granted, &$failed): void {
                foreach ($subscriptions as $subscription) {
                    try {
                        $granted += $ledger->grantMonthly($subscription) ? 1 : 0;
                    } catch (Throwable $exception) {
                        $failed++;

                        report($exception);
                    }
                }
            });

        $this->info("Added monthly credits to {$granted} subscriptions".($failed > 0 ? ", {$failed} failed." : '.'));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
