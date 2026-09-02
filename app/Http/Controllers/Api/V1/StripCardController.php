<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StripCardResource;
use App\Models\StripCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StripCardController extends Controller
{
    /**
     * The member's strip cards. Buying cards stays web-only (payments).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return StripCardResource::collection(
            StripCard::query()
                ->where('user_id', $request->user()->id)
                ->with(['club', 'plan'])
                ->latest('purchased_at')
                ->get()
        );
    }
}
