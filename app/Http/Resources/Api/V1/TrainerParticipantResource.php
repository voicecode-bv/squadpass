<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LessonEnrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member taking part in one occurrence, as shown to their trainer.
 *
 * @mixin LessonEnrollment
 */
class TrainerParticipantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user->name,
            'type' => $this->type->value,
        ];
    }
}
