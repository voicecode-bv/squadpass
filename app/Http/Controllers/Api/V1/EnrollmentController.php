<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Lessons\CancelEnrollment;
use App\Actions\Lessons\EnrollFixed;
use App\Actions\Lessons\EnrollSingle;
use App\Concerns\DescribesCreditOutcome;
use App\Enums\EnrollmentType;
use App\Exceptions\EnrollmentException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LessonEnrollmentResource;
use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EnrollmentController extends Controller
{
    use DescribesCreditOutcome;

    /**
     * The member's fixed enrollments plus upcoming one-time enrollments.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $fixed = LessonEnrollment::query()
            ->where('user_id', $request->user()->id)
            ->where('type', EnrollmentType::Fixed)
            ->with(['lesson.club'])
            ->get();

        $single = LessonEnrollment::query()
            ->where('user_id', $request->user()->id)
            ->where('type', EnrollmentType::Single)
            ->whereHas('occurrence', fn ($query) => $query->where('starts_at', '>=', now()))
            ->with(['occurrence.lesson', 'occurrence.club'])
            ->get()
            ->sortBy(fn (LessonEnrollment $enrollment) => $enrollment->occurrence->starts_at)
            ->values();

        return LessonEnrollmentResource::collection($fixed->concat($single));
    }

    public function storeSingle(Request $request, EnrollSingle $enrollSingle): LessonEnrollmentResource|JsonResponse
    {
        $validated = $request->validate([
            'occurrence_id' => ['required', 'integer'],
        ]);

        $occurrence = LessonOccurrence::query()->with(['lesson', 'club'])->findOrFail($request->integer('occurrence_id'));

        try {
            $enrollment = $enrollSingle->handle($request->user(), $occurrence);
        } catch (EnrollmentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new LessonEnrollmentResource($enrollment->load(['occurrence.lesson', 'occurrence.club']));
    }

    public function storeFixed(Request $request, EnrollFixed $enrollFixed): LessonEnrollmentResource|JsonResponse
    {
        $validated = $request->validate([
            'lesson_id' => ['required', 'integer'],
        ]);

        $lesson = Lesson::query()->with('club')->findOrFail($request->integer('lesson_id'));

        try {
            $enrollment = $enrollFixed->handle($request->user(), $lesson);
        } catch (EnrollmentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new LessonEnrollmentResource($enrollment->load(['lesson.club']));
    }

    public function destroy(Request $request, int $enrollmentId, CancelEnrollment $cancelEnrollment): JsonResponse
    {
        $enrollment = LessonEnrollment::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($enrollmentId);

        $outcome = $cancelEnrollment->handle($enrollment, $request->user());

        return response()->json([
            'message' => $this->creditOutcomeMessage(__('Enrollment cancelled.'), $outcome),
            'credits_refunded' => $outcome['refunded'],
            'credits_forfeited' => $outcome['forfeited'],
        ]);
    }
}
