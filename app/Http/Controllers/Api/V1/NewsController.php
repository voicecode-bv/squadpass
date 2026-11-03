<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NewsPostResource;
use App\Models\NewsPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NewsController extends Controller
{
    /**
     * The member's news feed across their clubs: pinned posts first, then newest first, paginated by cursor.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return NewsPostResource::collection(
            NewsPost::query()
                ->visible()
                ->whereIn('club_id', fn ($query) => $query
                    ->select('club_id')
                    ->from('memberships')
                    ->where('user_id', $request->user()->id)
                    ->where('status', MembershipStatus::Active->value))
                ->with(['club:id,name', 'author:id,name'])
                ->orderByDesc('pinned')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->cursorPaginate(20)
        );
    }
}
