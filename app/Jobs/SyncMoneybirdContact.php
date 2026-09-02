<?php

namespace App\Jobs;

use App\Actions\Moneybird\SyncMemberContact;
use App\Models\Membership;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncMoneybirdContact implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public Membership $membership) {}

    public function uniqueId(): string
    {
        return (string) $this->membership->id;
    }

    public function handle(SyncMemberContact $syncMemberContact): void
    {
        $syncMemberContact->handle($this->membership->fresh());
    }

    public function failed(Throwable $exception): void
    {
        $this->membership->club->recordMoneybirdError($exception->getMessage());
    }
}
