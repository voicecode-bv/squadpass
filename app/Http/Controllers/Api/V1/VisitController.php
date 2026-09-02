<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CheckInResource;
use App\Models\CheckIn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VisitController extends Controller
{
    /**
     * The member's check-in history, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return CheckInResource::collection(
            CheckIn::query()
                ->where('user_id', $request->user()->id)
                ->with(['club', 'occurrence.lesson'])
                ->latest('checked_in_at')
                ->paginate(25)
        );
    }
}
