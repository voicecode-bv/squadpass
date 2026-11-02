<?php

namespace App\Jobs;

use App\Actions\Moneybird\SyncMemberContact;
use App\Exceptions\MoneybirdContactConflict;
use App\Jobs\Middleware\ReleaseWhenMoneybirdRateLimited;
use App\Models\Membership;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncMoneybirdContact implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Real failures still give up after three attempts; Moneybird rate limits
     * only release the job and are bounded by retryUntil() instead.
     */
    public int $maxExceptions = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public Membership $membership) {}

    public function uniqueId(): string
    {
        return (string) $this->membership->id;
    }

    public function handle(SyncMemberContact $syncMemberContact): void
    {
        try {
            $syncMemberContact->handle($this->membership->fresh());
        } catch (MoneybirdContactConflict $exception) {
            // Retrying cannot fix a customer number that belongs to someone else.
            $this->fail($exception);
        }
    }

    /**
     * Large catch-ups can wait on Moneybird's rate limit for hours.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->plus(hours: 24);
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new ReleaseWhenMoneybirdRateLimited];
    }

    public function failed(Throwable $exception): void
    {
        $this->membership->club->recordMoneybirdError($exception->getMessage());
    }
}
