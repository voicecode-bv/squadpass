<?php

namespace App\Console\Commands;

use App\Actions\ClubSubscriptions\ChargeClubSubscription;
use App\Enums\SubscriptionStatus;
use App\Models\ClubSubscription;
use Illuminate\Console\Command;
use Throwable;

class ChargeDueClubSubscriptions extends Command
{
    protected $signature = 'club-subscriptions:charge';

    protected $description = 'Charge all active SquadPass plans whose billing date is due via the platform Mollie account';

    public function handle(ChargeClubSubscription $chargeClubSubscription): int
    {
        $charged = 0;
        $failed = 0;

        ClubSubscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('mollie_mandate_id')
            ->where('next_billing_at', '<=', today())
            ->with('club')
            ->chunkById(100, function ($subscriptions) use ($chargeClubSubscription, &$charged, &$failed) {
                foreach ($subscriptions as $subscription) {
                    try {
                        if ($chargeClubSubscription->handle($subscription)) {
                            $charged++;
                        }
                    } catch (Throwable $exception) {
                        $failed++;

                        report($exception);
                    }
                }
            });

        $this->info("Charged {$charged} club subscriptions".($failed > 0 ? ", {$failed} failed." : '.'));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
