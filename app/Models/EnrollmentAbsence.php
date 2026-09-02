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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['lesson_enrollment_id', 'lesson_occurrence_id'])]
class EnrollmentAbsence extends Model
{
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
