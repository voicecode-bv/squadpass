<?php

namespace App\Models;

use Database\Factories\TicketMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['ticket_id', 'user_id', 'body'])]
class TicketMessage extends Model
{
    /** @use HasFactory<TicketMessageFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Every message, from any entry point, moves the ticket's unread marker; the author has read it by writing it.
        static::created(function (TicketMessage $message): void {
            Ticket::query()->whereKey($message->ticket_id)->update([
                'last_message_at' => $message->created_at,
                'last_message_by' => $message->user_id,
            ]);
        });
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this message was written by the member who opened the ticket.
     */
    public function isFromTicketOwner(): bool
    {
        return $this->user_id === $this->ticket->user_id;
    }
}
