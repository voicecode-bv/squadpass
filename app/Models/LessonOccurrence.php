<?php

namespace App\Models;

use App\Enums\LessonOccurrenceStatus;
use Carbon\CarbonInterface;
use Database\Factories\LessonOccurrenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lesson_id
 * @property int $club_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property LessonOccurrenceStatus $status
 * @property int|null $capacity_override
 * @property int $booked_count
 * @property Carbon|null $reminders_sent_at set once the lesson reminders went out
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['lesson_id', 'club_id', 'starts_at', 'ends_at', 'status', 'capacity_override', 'booked_count', 'reminders_sent_at'])]
class LessonOccurrence extends Model
{
    /** @use HasFactory<LessonOccurrenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => LessonOccurrenceStatus::class,
            'capacity_override' => 'integer',
            'booked_count' => 'integer',
            'reminders_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * Single enrollments directly on this occurrence.
     *
     * @return HasMany<LessonEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(LessonEnrollment::class);
    }

    /**
     * Everyone attending: single enrollments plus fixed enrollments minus absences.
     *
     * @return Collection<int, LessonEnrollment>
     */
    public function participants(): Collection
    {
        return LessonEnrollment::query()
            ->with('user')
            ->where(function (Builder $query): void {
                $query->where('lesson_occurrence_id', $this->id)
                    ->orWhere(function (Builder $fixedQuery): void {
                        $fixedQuery->where('lesson_id', $this->lesson_id)
                            ->whereDoesntHave('absences', fn ($absenceQuery) => $absenceQuery
                                ->where('lesson_occurrence_id', $this->id));
                    });
            })
            ->get();
    }

    public function effectiveCapacity(): int
    {
        return $this->capacity_override ?? $this->lesson->capacity;
    }

    /**
     * Atomically claim a spot. Returns false when the occurrence is full
     * (or not bookable), without racing concurrent claims.
     */
    public function claimSpot(): bool
    {
        $claimed = static::query()
            ->whereKey($this->id)
            ->where('status', LessonOccurrenceStatus::Scheduled)
            ->whereRaw('booked_count < COALESCE(capacity_override, (select capacity from lessons where lessons.id = lesson_occurrences.lesson_id))')
            ->increment('booked_count') === 1;

        if ($claimed) {
            $this->booked_count++;
        }

        return $claimed;
    }

    public function releaseSpot(): void
    {
        $released = static::query()
            ->whereKey($this->id)
            ->where('booked_count', '>', 0)
            ->decrement('booked_count') === 1;

        if ($released && $this->booked_count > 0) {
            $this->booked_count--;
        }
    }

    public function isCancelled(): bool
    {
        return $this->status === LessonOccurrenceStatus::Cancelled;
    }

    /**
     * The last moment a member can cancel and still get their credit back.
     */
    public function freeCancellationUntil(): CarbonInterface
    {
        return $this->starts_at->copy()->subHours($this->club->cancellation_deadline_hours);
    }

    public function isFreeToCancel(): bool
    {
        return now()->lt($this->freeCancellationUntil());
    }
}
