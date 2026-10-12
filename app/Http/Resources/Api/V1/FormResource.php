<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A form as the member sees it. The controller attaches `member_state` to
 * every form and, on the detail endpoint, `member_fields` and
 * `member_answers` for the version that member must be shown.
 *
 * @mixin Form
 */
class FormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'club' => new ClubResource($this->whenLoaded('club')),
            'locked' => $this->whenHas('member_state', fn (array $state): bool => $state['locked']),
            'submitted_at' => $this->whenHas('member_state', fn (array $state): ?string => $state['submitted_at']),
            'fields' => $this->whenHas('member_fields', fn (array $fields) => FormFieldResource::collection($fields)),
            'answers' => $this->whenHas('member_answers'),
        ];
    }
}
