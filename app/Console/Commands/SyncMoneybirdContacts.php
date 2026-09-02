<?php

namespace App\Console\Commands;

use App\Enums\MembershipStatus;
use App\Jobs\SyncMoneybirdContact;
use App\Models\Club;
use Illuminate\Console\Command;

class SyncMoneybirdContacts extends Command
{
    protected $signature = 'moneybird:sync-contacts {club : Club slug} {--all : Include ended memberships}';

    protected $description = 'Queue a Moneybird contact sync for every member of a club';

    public function handle(): int
    {
        $club = Club::query()->where('slug', $this->argument('club'))->first();

        if (! $club) {
            $this->error('Club not found.');

            return self::FAILURE;
        }

        if (! $club->hasMoneybirdConfigured()) {
            $this->error('Moneybird is not configured for this club.');

            return self::FAILURE;
        }

        $queued = 0;

        $club->memberships()
            ->when(! $this->option('all'), fn ($query) => $query->where('status', MembershipStatus::Active))
            ->chunkById(200, function ($memberships) use (&$queued): void {
                foreach ($memberships as $membership) {
                    SyncMoneybirdContact::dispatch($membership);
                    $queued++;
                }
            });

        $this->info("Queued {$queued} contact syncs.");

        return self::SUCCESS;
    }
}
