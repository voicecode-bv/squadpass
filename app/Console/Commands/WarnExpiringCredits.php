<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\CreditGrant;
use App\Models\User;
use App\Notifications\CreditsExpiring;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class WarnExpiringCredits extends Command
{
    /** How long before the expiry members are warned. */
    public const int DAYS_AHEAD = 3;

    protected $signature = 'credits:warn-expiring';

    protected $description = 'Warn members a few days before their unused lesson credits expire';

    public function handle(): int
    {
        $cutoff = now()->addDays(self::DAYS_AHEAD);
        $warned = 0;

        // One email per member per club, however many grants expire in the window.
        $this->expiringGrants($cutoff)
            ->selectRaw('user_id, club_id, SUM(remaining) as credits, MIN(expires_at) as first_expiry')
            ->groupBy('user_id', 'club_id')
            ->toBase()
            ->cursor()
            ->each(function (object $group) use ($cutoff, &$warned): void {
                // Claiming the grants first keeps a rerun from warning twice.
                $claimed = $this->expiringGrants($cutoff)
                    ->where('user_id', $group->user_id)
                    ->where('club_id', $group->club_id)
                    ->update(['expiry_warned_at' => now()]);

                $user = User::query()->find((int) $group->user_id);
                $club = Club::query()->find((int) $group->club_id);

                if ($claimed === 0 || $user === null || $club === null) {
                    return;
                }

                $user->notify(new CreditsExpiring($club, (int) $group->credits, Carbon::parse($group->first_expiry)));
                $warned++;
            });

        $this->info("Warned {$warned} members about expiring credits.");

        return self::SUCCESS;
    }

    /**
     * @return Builder<CreditGrant>
     */
    private function expiringGrants(CarbonInterface $cutoff): Builder
    {
        return CreditGrant::query()
            ->whereNull('expiry_warned_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', $cutoff)
            ->where('remaining', '>', 0);
    }
}
