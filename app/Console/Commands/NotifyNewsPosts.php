<?php

namespace App\Console\Commands;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\NewsPost;
use App\Notifications\NewsPublished;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyNewsPosts extends Command
{
    protected $signature = 'news:notify';

    protected $description = 'Email members about news posts that were published with "email members" on';

    public function handle(): int
    {
        $notified = 0;

        NewsPost::query()
            ->where('notify_members', true)
            ->whereNull('members_notified_at')
            ->where('published_at', '<=', now())
            ->with('club')
            ->chunkById(50, function ($posts) use (&$notified): void {
                foreach ($posts as $post) {
                    $notified += $this->notifyMembers($post);
                }
            });

        $this->info("Emailed {$notified} members about news posts.");

        return self::SUCCESS;
    }

    /**
     * Claim the post before sending, so a rerun or overlapping run never emails twice.
     */
    private function notifyMembers(NewsPost $post): int
    {
        $claimed = NewsPost::query()
            ->whereKey($post->id)
            ->whereNull('members_notified_at')
            ->update(['members_notified_at' => now()]) === 1;

        if (! $claimed) {
            return 0;
        }

        $count = 0;

        Membership::query()
            ->where('club_id', $post->club_id)
            ->where('status', MembershipStatus::Active)
            ->with('user')
            ->chunkById(500, function ($memberships) use ($post, &$count): void {
                $users = $memberships->pluck('user')->filter();

                Notification::send($users, new NewsPublished($post));
                $count += $users->count();
            });

        return $count;
    }
}
