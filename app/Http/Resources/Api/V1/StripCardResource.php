<?php

namespace App\Http\Resources\Api\V1;

use App\Models\StripCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StripCard
 */
class StripCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club' => new ClubResource($this->whenLoaded('club')),
            'plan_name' => $this->plan->name,
            'strips_total' => $this->strips_total,
            'strips_remaining' => $this->strips_remaining,
            'is_usable' => $this->isUsable(),
            'purchased_at' => $this->purchased_at->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
        ];
    }
}
