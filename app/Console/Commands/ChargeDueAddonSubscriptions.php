<?php

namespace App\Console\Commands;

use App\Actions\Addons\ChargeAddonSubscription;
use App\Enums\SubscriptionStatus;
use App\Models\AddonSubscription;
use Illuminate\Console\Command;
use Throwable;

class ChargeDueAddonSubscriptions extends Command
{
    protected $signature = 'addons:charge';

    protected $description = 'Charge all active add-on subscriptions whose billing date is due via the platform Mollie account';

    public function handle(ChargeAddonSubscription $chargeAddonSubscription): int
    {
        $charged = 0;
        $failed = 0;

        AddonSubscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('mollie_mandate_id')
            ->where('next_billing_at', '<=', today())
            ->with(['addon', 'club'])
            ->chunkById(100, function ($subscriptions) use ($chargeAddonSubscription, &$charged, &$failed) {
                foreach ($subscriptions as $subscription) {
                    try {
                        if ($chargeAddonSubscription->handle($subscription)) {
                            $charged++;
                        }
                    } catch (Throwable $exception) {
                        $failed++;

                        report($exception);
                    }
                }
            });

        $this->info("Charged {$charged} add-on subscriptions".($failed > 0 ? ", {$failed} failed." : '.'));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
