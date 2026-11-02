<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneDemoSandboxes extends Command
{
    protected $signature = 'demo:prune';

    protected $description = 'Delete expired demo sandboxes: their clubs (with everything in them) and their fake accounts';

    /**
     * Clubs go first: their rows cascade at the database level, so one delete
     * per club removes members, payments, lessons and check-ins. The accounts
     * follow in chunks. Only rows with an expiry are touched, so real clubs and
     * a curated demo club (is_demo without expiry) are never affected.
     */
    public function handle(): int
    {
        $clubs = 0;

        Club::query()
            ->whereNotNull('demo_expires_at')
            ->where('demo_expires_at', '<=', now())
            ->select(['id', 'photo_path'])
            ->lazyById(100)
            ->each(function (Club $club) use (&$clubs): void {
                if ($club->photo_path !== null) {
                    Storage::disk('public')->delete($club->photo_path);
                }

                $club->delete();
                $clubs++;
            });

        $users = 0;

        do {
            $deleted = User::query()
                ->whereNotNull('demo_expires_at')
                ->where('demo_expires_at', '<=', now())
                ->limit(1000)
                ->delete();

            $users += $deleted;
        } while ($deleted > 0);

        $this->info("Deleted {$clubs} demo clubs and {$users} demo accounts.");

        return self::SUCCESS;
    }
}
