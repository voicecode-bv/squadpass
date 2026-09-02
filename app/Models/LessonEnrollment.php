<?php

namespace App\Models;

use App\Enums\EnrollmentType;
use Database\Factories\LessonEnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property int $user_id
 * @property EnrollmentType $type
 * @property int|null $lesson_id
 * @property int|null $lesson_occurrence_id
 * @property int|null $subscription_id
 * @property int $credits_used
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['club_id', 'user_id', 'type', 'lesson_id', 'lesson_occurrence_id', 'subscription_id', 'credits_used'])]
class LessonEnrollment extends Model
{
    /** @use HasFactory<LessonEnrollmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EnrollmentType::class,
            'credits_used' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return BelongsTo<LessonOccurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(LessonOccurrence::class, 'lesson_occurrence_id');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return HasMany<EnrollmentAbsence, $this>
     */
    public function absences(): HasMany
    {
        return $this->hasMany(EnrollmentAbsence::class);
    }

    public function isFixed(): bool
    {
        return $this->type === EnrollmentType::Fixed;
    }
}
