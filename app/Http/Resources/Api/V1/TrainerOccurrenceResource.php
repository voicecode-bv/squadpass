<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LessonOccurrence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An occurrence of a lesson the authenticated trainer is assigned to.
 *
 * @mixin LessonOccurrence
 */
class TrainerOccurrenceResource extends JsonResource
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
            'club_name' => $this->lesson->club->name,
            'club_timezone' => $this->lesson->club->timezone,
            'room' => $this->lesson->room?->name,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'capacity' => $this->effectiveCapacity(),
            'booked_count' => $this->booked_count,
        ];
    }
}
