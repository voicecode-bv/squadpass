<?php

namespace App\Console\Commands;

use App\Models\Club;
use Database\Seeders\DemoClubSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateDemoClub extends Command
{
    protected $signature = 'demo:populate {club : Club slug} {--members=45 : Number of fake members to add}';

    protected $description = 'Fill a demo club with fake members, payments, check-ins, enrollments and tickets';

    /**
     * Only clubs marked as demo can be populated, so a real club never gets
     * fake members. Runs in one transaction: a failure leaves nothing behind.
     */
    public function handle(DemoClubSeeder $demoClubSeeder): int
    {
        $club = Club::query()->where('slug', $this->argument('club'))->first();

        if (! $club) {
            $this->error('Club not found.');

            return self::FAILURE;
        }

        if (! $club->is_demo) {
            $this->error('This club is not a demo club. Mark it as demo club first.');

            return self::FAILURE;
        }

        $memberCount = filter_var($this->option('members'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);

        if ($memberCount === false) {
            $this->error('--members must be a number between 1 and 1000.');

            return self::FAILURE;
        }

        $owner = $club->owners()->first();

        if (! $owner) {
            $this->error('The club has no owner to assign tickets to.');

            return self::FAILURE;
        }

        $added = DB::transaction(fn (): int => $demoClubSeeder->populate($club, $owner, $memberCount));

        $this->info("Added {$memberCount} members to {$club->name}, {$added} of them active.");

        return self::SUCCESS;
    }
}
