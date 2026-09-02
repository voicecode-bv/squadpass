<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LessonEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LessonEnrollment
 */
class LessonEnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'lesson_id' => $this->lesson_id,
            'lesson_name' => $this->lesson?->name ?? $this->occurrence?->lesson->name,
            'club_id' => $this->club_id,
            'occurrence' => new LessonOccurrenceResource($this->whenLoaded('occurrence')),
        ];
    }
}
