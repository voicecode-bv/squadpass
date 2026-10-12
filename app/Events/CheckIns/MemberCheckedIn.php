<?php

namespace App\Events\CheckIns;

use App\Models\CheckIn;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A member checked themselves in by scanning a club's kiosk code.
 *
 * The kiosk screen listens for this to confirm the scan. It broadcasts on the
 * spot rather than through the queue, so the confirmation does not wait on a
 * worker; the caller swallows failures, because nothing about the check-in
 * depends on the screen noticing, and the connection is bounded by the
 * timeouts on the reverb connection.
 */
class MemberCheckedIn implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CheckIn $checkIn) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("clubs.{$this->checkIn->club_id}.check-ins");
    }

    public function broadcastAs(): string
    {
        return 'member.checked-in';
    }

    /**
     * @return array{name: string, coverage: string}
     */
    public function broadcastWith(): array
    {
        return [
            'name' => $this->checkIn->user->name,
            'coverage' => $this->checkIn->coverageLabel(),
        ];
    }
}
