<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    /**
     * The member's subscriptions. Subscribing stays web-only (payments).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return SubscriptionResource::collection(
            Subscription::query()
                ->where('user_id', $request->user()->id)
                ->with(['club', 'plan'])
                ->latest('starts_at')
                ->get()
        );
    }
}
