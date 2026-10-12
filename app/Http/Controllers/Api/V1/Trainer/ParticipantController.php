<?php

namespace App\Http\Controllers\Api\V1\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TrainerParticipantResource;
use App\Models\LessonOccurrence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ParticipantController extends Controller
{
    /**
     * The members taking part in one occurrence. Only occurrences of lessons
     * this trainer is assigned to are reachable; anything else is a 404.
     */
    public function index(Request $request, int $occurrenceId): AnonymousResourceCollection
    {
        $occurrence = LessonOccurrence::query()
            ->whereHas('lesson.trainers', fn ($query) => $query->where('user_id', $request->user()->id))
            ->findOrFail($occurrenceId);

        return TrainerParticipantResource::collection(
            $occurrence->participants()->sortBy(fn ($enrollment) => $enrollment->user->name)->values()
        );
    }
}
