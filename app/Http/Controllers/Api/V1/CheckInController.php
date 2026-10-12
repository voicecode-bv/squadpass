<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CheckIns\CheckInWithCode;
use App\Exceptions\CheckInException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CheckInResource;
use App\Services\CheckIns\CheckInCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    /**
     * Check the member in from a club kiosk code scanned in the app.
     */
    public function store(Request $request, CheckInWithCode $checkInWithCode): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        try {
            $checkIn = $checkInWithCode->fromClubCode($request->user(), $validated['code']);
        } catch (CheckInException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new CheckInResource($checkIn->load('club')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * The member's own rotating code, for the QR the desk scans.
     *
     * `expires_in` is the number of seconds the returned code stays valid, so
     * the app knows when to ask for the next one; `image_url` renders the same
     * code as a QR image the app can show as-is.
     */
    public function code(Request $request, CheckInCode $codes): JsonResponse
    {
        $code = $codes->forMember($request->user());

        return response()->json([
            'code' => $code,
            'expires_in' => $codes->secondsUntilRotation(),
            'image_url' => route('check-ins.code-image', ['code' => $code]),
        ]);
    }
}
