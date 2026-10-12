<?php

namespace App\Http\Controllers\Api\V1\Trainer;

use App\Enums\LessonOccurrenceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrainerOccurrenceResource;
use App\Models\LessonOccurrence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LessonController extends Controller
{
    /**
     * Upcoming occurrences of the lessons this trainer is assigned to,
     * for the next four weeks.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return TrainerOccurrenceResource::collection(
            LessonOccurrence::query()
                ->whereHas('lesson.trainers', fn ($query) => $query->where('user_id', $request->user()->id))
                ->where('status', LessonOccurrenceStatus::Scheduled)
                ->whereBetween('starts_at', [now(), now()->addWeeks(4)])
                ->with(['lesson.club', 'lesson.room'])
                ->orderBy('starts_at')
                ->get()
        );
    }
}
