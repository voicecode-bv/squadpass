<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ApplyPaymentStatus;
use App\Models\Club;
use App\Models\Payment;
use App\Services\Mollie\MollieGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MollieWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        Club $club,
        MollieGateway $gateway,
        ApplyPaymentStatus $applyPaymentStatus,
    ): Response {
        $molliePaymentId = $request->input('id');

        abort_unless(is_string($molliePaymentId) && $molliePaymentId !== '', 400);

        $payment = Payment::query()
            ->where('club_id', $club->id)
            ->where('mollie_payment_id', $molliePaymentId)
            ->first();

        abort_unless($payment !== null, 404);

        // The webhook payload only carries an id; always verify the state server-side.
        $result = $gateway->getPayment($club, $molliePaymentId);

        $applyPaymentStatus->handle($payment, $result);

        return response()->noContent();
    }
}
