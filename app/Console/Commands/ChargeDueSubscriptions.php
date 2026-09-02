<?php

namespace App\Console\Commands;

use App\Actions\Payments\ChargeSubscription;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Throwable;

class ChargeDueSubscriptions extends Command
{
    protected $signature = 'subscriptions:charge';

    protected $description = 'Charge all active subscriptions whose billing date is due via their Mollie mandate';

    public function handle(ChargeSubscription $chargeSubscription): int
    {
        $charged = 0;
        $failed = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('mollie_mandate_id')
            ->where('next_billing_at', '<=', today())
            ->with(['plan', 'club'])
            ->chunkById(100, function ($subscriptions) use ($chargeSubscription, &$charged, &$failed) {
                foreach ($subscriptions as $subscription) {
                    try {
                        if ($chargeSubscription->handle($subscription)) {
                            $charged++;
                        }
                    } catch (Throwable $exception) {
                        $failed++;

                        report($exception);
                    }
                }
            });

        $this->info("Charged {$charged} subscriptions".($failed > 0 ? ", {$failed} failed." : '.'));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
