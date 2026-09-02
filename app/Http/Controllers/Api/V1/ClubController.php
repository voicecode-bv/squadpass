<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClubResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClubController extends Controller
{
    /**
     * Clubs where the authenticated member has a membership.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ClubResource::collection(
            $request->user()->memberClubs()->orderBy('name')->get()
        );
    }
}
