<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Club;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Club
 *
 * @property-read Pivot $pivot the membership, when listed through a user's clubs
 */
class ClubResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'cancellation_deadline_hours' => $this->cancellation_deadline_hours,
            'credit_expiry_days' => $this->credit_expiry_days,
            'membership_number' => $this->whenPivotLoaded('memberships', fn () => $this->pivot->getAttribute('membership_number')),
            'membership_status' => $this->whenPivotLoaded('memberships', fn () => $this->pivot->getAttribute('status')),
        ];
    }
}
