<?php

namespace App\Console\Commands;

use App\Models\CreditGrant;
use App\Services\Credits\CreditLedger;
use Illuminate\Console\Command;
use Throwable;

class ExpireCredits extends Command
{
    protected $signature = 'credits:expire';

    protected $description = 'Remove unused lesson credits whose expiry date has passed from the member balances';

    public function handle(CreditLedger $ledger): int
    {
        $expired = 0;
        $failed = 0;

        CreditGrant::query()
            ->where('remaining', '>', 0)
            ->where('expires_at', '<=', now())
            ->chunkById(200, function ($grants) use ($ledger, &$expired, &$failed): void {
                foreach ($grants as $grant) {
                    try {
                        $ledger->expire($grant);
                        $expired++;
                    } catch (Throwable $exception) {
                        $failed++;

                        report($exception);
                    }
                }
            });

        $this->info("Expired {$expired} credit grants".($failed > 0 ? ", {$failed} failed." : '.'));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
