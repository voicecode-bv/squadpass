<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentType;
use App\Enums\LessonOccurrenceStatus;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LessonOccurrenceResource;
use App\Models\EnrollmentAbsence;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScheduleController extends Controller
{
    /**
     * Upcoming occurrences at the member's clubs for the next four weeks,
     * including the member's own enrollment state per occurrence.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $clubIds = $user->memberships()
            ->where('status', MembershipStatus::Active)
            ->pluck('club_id');

        $occurrences = LessonOccurrence::query()
            ->whereIn('club_id', $clubIds)
            ->where('status', LessonOccurrenceStatus::Scheduled)
            ->whereBetween('starts_at', [now(), now()->addWeeks(4)])
            ->with(['lesson.trainers.user', 'lesson.room', 'club'])
            ->orderBy('starts_at')
            ->get();

        // Keyed by occurrence / lesson so the app can cancel straight from the
        // schedule, without first fetching the enrollment list to find the id.
        $singleEnrollmentIds = LessonEnrollment::query()
            ->where('user_id', $user->id)
            ->where('type', EnrollmentType::Single)
            ->pluck('id', 'lesson_occurrence_id');

        $fixedEnrollmentIds = LessonEnrollment::query()
            ->where('user_id', $user->id)
            ->where('type', EnrollmentType::Fixed)
            ->pluck('id', 'lesson_id');

        $absentOccurrenceIds = EnrollmentAbsence::query()
            ->whereHas('enrollment', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('lesson_occurrence_id')
            ->flip();

        $occurrences->each(fn (LessonOccurrence $occurrence) => $occurrence->setAttribute('enrollment_state', [
            'enrolled_single' => $singleEnrollmentIds->has($occurrence->id),
            'enrolled_fixed' => $fixedEnrollmentIds->has($occurrence->lesson_id),
            'absent' => $absentOccurrenceIds->has($occurrence->id),
            'enrollment_id' => $singleEnrollmentIds->get($occurrence->id)
                ?? $fixedEnrollmentIds->get($occurrence->lesson_id),
        ]));

        return LessonOccurrenceResource::collection($occurrences);
    }
}
