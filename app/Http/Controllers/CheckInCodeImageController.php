<?php

namespace App\Http\Controllers;

use App\Exceptions\CheckInException;
use App\Services\CheckIns\CheckInCode;
use App\Services\CheckIns\QrCodeRenderer;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The QR image for a member's own check-in code, so the mobile app can show it
 * as a plain image instead of shipping a QR encoder.
 *
 * The route is public on purpose: the code in the URL is signed and expires
 * within seconds, and the image it renders is exactly what the member is
 * already holding up to the camera. Anything that is not a live member code is
 * a 404.
 */
class CheckInCodeImageController extends Controller
{
    public function __invoke(string $code, CheckInCode $codes, QrCodeRenderer $renderer): Response
    {
        try {
            $codes->memberFrom($code);
        } catch (CheckInException) {
            throw new NotFoundHttpException;
        }

        return response($renderer->png($code), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
