<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use Database\Factories\CreditTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One append-only line in a member's credit timeline.
 *
 * @property int $id
 * @property int $club_id
 * @property int $user_id
 * @property int $subscription_id
 * @property int|null $credit_grant_id
 * @property CreditTransactionType $type
 * @property int $amount signed change to the balance
 * @property int $balance_after
 * @property int|null $lesson_id
 * @property int|null $lesson_occurrence_id
 * @property int|null $lesson_enrollment_id
 * @property int|null $actor_id user who caused it; null for the system
 * @property Carbon $created_at
 */
#[Fillable([
    'club_id', 'user_id', 'subscription_id', 'credit_grant_id', 'type', 'amount', 'balance_after',
    'lesson_id', 'lesson_occurrence_id', 'lesson_enrollment_id', 'actor_id',
])]
class CreditTransaction extends Model
{
    /** @use HasFactory<CreditTransactionFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
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
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<CreditGrant, $this>
     */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(CreditGrant::class, 'credit_grant_id');
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
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
