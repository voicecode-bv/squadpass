<?php

namespace App\Http\Resources\Api\V1;

use App\Models\NewsPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NewsPost
 */
class NewsPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'club_id' => $this->club_id,
            'club_name' => $this->club->name,
            'title' => $this->title,
            'body' => $this->body,
            'pinned' => $this->pinned,
            'author_name' => $this->author?->name,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
