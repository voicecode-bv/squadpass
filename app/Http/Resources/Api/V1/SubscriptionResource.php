<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
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
            'price_cents' => $this->plan->price_cents,
            'interval' => $this->plan->interval->value,
            'interval_label' => $this->plan->interval->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'credits_remaining' => $this->credits_remaining,
            'is_unlimited' => $this->isUnlimited(),
            'starts_at' => $this->starts_at->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'next_billing_at' => $this->next_billing_at?->toDateString(),
        ];
    }
}
