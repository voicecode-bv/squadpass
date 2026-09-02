<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LessonOccurrence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LessonOccurrence
 */
class LessonOccurrenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'lesson_name' => $this->lesson->name,
            'club' => new ClubResource($this->whenLoaded('club')),
            'room' => $this->lesson->room?->name,
            'trainers' => $this->lesson->trainers->map(fn ($trainer) => $trainer->user->name)->values(),
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'status' => $this->status->value,
            'capacity' => $this->effectiveCapacity(),
            'spots_left' => max(0, $this->effectiveCapacity() - $this->booked_count),
            'enrollment' => $this->whenHas('enrollment_state'),
        ];
    }
}
