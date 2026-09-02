<?php

namespace App\Http\Controllers;

use App\Actions\Addons\ApplyAddonPaymentStatus;
use App\Models\AddonPayment;
use App\Services\Mollie\MollieGateway;
use App\Services\Mollie\PlatformMollieAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook for payments on the platform's own Mollie account (add-on billing).
 */
class PlatformMollieWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MollieGateway $gateway,
        PlatformMollieAccount $platform,
        ApplyAddonPaymentStatus $applyAddonPaymentStatus,
    ): Response {
        $molliePaymentId = $request->input('id');

        abort_unless(is_string($molliePaymentId) && $molliePaymentId !== '', 400);

        $payment = AddonPayment::query()->where('mollie_payment_id', $molliePaymentId)->first();

        abort_unless($payment !== null, 404);

        // The webhook payload only carries an id; always verify the state server-side.
        $result = $gateway->getPayment($platform, $molliePaymentId);

        $applyAddonPaymentStatus->handle($payment, $result);

        return response()->noContent();
    }
}
