<?php

namespace App\Models;

use App\Enums\StaffPermission;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $club_id
 * @property int $user_id
 * @property int|null $assigned_to
 * @property int $number
 * @property string $subject
 * @property TicketCategory $category
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property Carbon|null $last_message_at null for tickets without a message since unread tracking started
 * @property int|null $last_message_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['club_id', 'user_id', 'assigned_to', 'number', 'subject', 'category', 'status', 'priority'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * The member who opened the ticket.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The staff member handling the ticket.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /**
     * The human-readable ticket reference, e.g. "TKT-1042".
     */
    public function reference(): string
    {
        return 'TKT-'.$this->number;
    }

    public function isClosed(): bool
    {
        return $this->status === TicketStatus::Closed;
    }

    /**
     * Remember that the user has seen everything in this ticket up to now.
     */
    public function markReadBy(User $user): void
    {
        TicketRead::query()->upsert(
            [['ticket_id' => $this->id, 'user_id' => $user->id, 'last_read_at' => now()]],
            ['ticket_id', 'user_id'],
            ['last_read_at'],
        );
    }

    /**
     * Whether this ticket's latest message would open in the member portal for this user
     * (their own ticket) rather than in the club's ticket desk.
     */
    public function opensInPortalFor(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Tickets with a message the user has not seen: their own tickets and the
     * tickets of clubs where they handle support, where someone else wrote last.
     *
     * @param  Builder<self>  $query
     */
    protected function scopeUnreadFor(Builder $query, User $user): void
    {
        $staffClubIds = collect($user->staffClubIdsWith(StaffPermission::Tickets));

        $query
            ->whereNotNull('tickets.last_message_at')
            ->where(fn (Builder $author) => $author->whereNull('tickets.last_message_by')->orWhere('tickets.last_message_by', '<>', $user->id))
            ->where(fn (Builder $visible) => $visible
                ->where('tickets.user_id', $user->id)
                ->when($staffClubIds->isNotEmpty(), fn (Builder $staff) => $staff->orWhereIn('tickets.club_id', $staffClubIds)))
            ->whereNotExists(fn (QueryBuilder $read) => $read
                ->select(DB::raw(1))
                ->from('ticket_reads')
                ->whereColumn('ticket_reads.ticket_id', 'tickets.id')
                ->where('ticket_reads.user_id', $user->id)
                ->whereColumn('ticket_reads.last_read_at', '>=', 'tickets.last_message_at'));
    }
}
