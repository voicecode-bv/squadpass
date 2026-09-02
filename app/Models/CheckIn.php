<?php

namespace App\Models;

use Database\Factories\CheckInFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property int $user_id
 * @property int|null $lesson_occurrence_id
 * @property int|null $subscription_id
 * @property int|null $strip_card_id
 * @property int|null $checked_in_by
 * @property Carbon $checked_in_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'club_id', 'user_id', 'lesson_occurrence_id', 'subscription_id',
    'strip_card_id', 'checked_in_by', 'checked_in_at',
])]
class CheckIn extends Model
{
    /** @use HasFactory<CheckInFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
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
     * @return BelongsTo<StripCard, $this>
     */
    public function stripCard(): BelongsTo
    {
        return $this->belongsTo(StripCard::class);
    }

    public function coverageLabel(): string
    {
        return match (true) {
            $this->strip_card_id !== null => __('Strip card'),
            $this->subscription_id !== null => __('Subscription'),
            $this->lesson_occurrence_id !== null => __('Lesson enrollment'),
            default => __('Visit'),
        };
    }
}
