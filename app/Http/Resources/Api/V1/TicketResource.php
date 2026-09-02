<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'number' => $this->number,
            'subject' => $this->subject,
            'category' => $this->category->value,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'club' => new ClubResource($this->whenLoaded('club')),
            'assignee' => $this->assignee?->name,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'messages' => TicketMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
