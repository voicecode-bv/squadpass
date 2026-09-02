<?php

namespace App\Jobs;

use App\Actions\Moneybird\CreateInvoiceForPayment;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateMoneybirdInvoice implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function handle(CreateInvoiceForPayment $createInvoiceForPayment): void
    {
        $createInvoiceForPayment->handle($this->payment->fresh());
    }
}
