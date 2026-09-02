<?php

namespace App\Models;

use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property string $name
 * @property string|null $description
 * @property int|null $room_id
 * @property int $capacity
 * @property int $day_of_week ISO-8601: 1 = Monday ... 7 = Sunday
 * @property string $start_time
 * @property int $duration_minutes
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'club_id', 'name', 'description', 'room_id', 'capacity',
    'day_of_week', 'start_time', 'duration_minutes', 'starts_on', 'ends_on', 'active',
])]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'day_of_week' => 'integer',
            'duration_minutes' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'active' => 'boolean',
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
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsToMany<Trainer, $this>
     */
    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(Trainer::class)->withTimestamps();
    }

    /**
     * @return HasMany<LessonOccurrence, $this>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(LessonOccurrence::class);
    }

    /**
     * Fixed enrollments on this lesson.
     *
     * @return HasMany<LessonEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(LessonEnrollment::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('active', true);
    }
}
