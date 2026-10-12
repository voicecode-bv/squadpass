<?php

namespace App\Events\CheckIns;

use App\Models\Club;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A member scanned a club's kiosk code but was not let in.
 *
 * Only refusals that belong to a known club travel: a code that does not
 * verify names no club, and a club's screen should not be paintable by anyone
 * posting a made-up code. The member always gets the reason in the app; this
 * is so the screen they are standing at says the same thing.
 */
class CheckInRefused implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Club $club,
        public User $member,
        public string $reason,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("clubs.{$this->club->id}.check-ins");
    }

    public function broadcastAs(): string
    {
        return 'check-in.refused';
    }

    /**
     * @return array{name: string, reason: string}
     */
    public function broadcastWith(): array
    {
        return [
            'name' => $this->member->name,
            'reason' => $this->reason,
        ];
    }
}
