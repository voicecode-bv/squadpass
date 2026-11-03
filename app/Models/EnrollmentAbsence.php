<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lesson_enrollment_id
 * @property int $lesson_occurrence_id
 * @property bool $credit_forfeited the credit stayed spent because the absence was reported after the cancellation deadline
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['lesson_enrollment_id', 'lesson_occurrence_id', 'credit_forfeited'])]
class EnrollmentAbsence extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_forfeited' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<LessonEnrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(LessonEnrollment::class, 'lesson_enrollment_id');
    }

    /**
     * @return BelongsTo<LessonOccurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(LessonOccurrence::class, 'lesson_occurrence_id');
    }
}
