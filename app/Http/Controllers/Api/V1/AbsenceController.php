<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Lessons\MarkAbsence;
use App\Concerns\DescribesCreditOutcome;
use App\Exceptions\EnrollmentException;
use App\Http\Controllers\Controller;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    use DescribesCreditOutcome;

    /**
     * Mark the member absent for one occurrence of their fixed enrollment.
     */
    public function store(Request $request, MarkAbsence $markAbsence): JsonResponse
    {
        $validated = $request->validate([
            'occurrence_id' => ['required', 'integer'],
        ]);

        $occurrence = LessonOccurrence::query()->findOrFail($request->integer('occurrence_id'));

        $enrollment = LessonEnrollment::query()
            ->where('user_id', $request->user()->id)
            ->where('lesson_id', $occurrence->lesson_id)
            ->firstOrFail();

        try {
            $outcome = $markAbsence->handle($enrollment, $occurrence, $request->user());
        } catch (EnrollmentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => $this->creditOutcomeMessage(__('Marked absent for this lesson.'), $outcome),
            'credits_refunded' => $outcome['refunded'],
            'credits_forfeited' => $outcome['forfeited'],
        ]);
    }
}
