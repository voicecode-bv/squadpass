<?php

namespace App\Jobs;

use App\Actions\Moneybird\CreateInvoiceForPayment;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CreateMoneybirdInvoice implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public Payment $payment) {}

    public function handle(CreateInvoiceForPayment $createInvoiceForPayment): void
    {
        $createInvoiceForPayment->handle($this->payment->fresh());
    }

    public function failed(Throwable $exception): void
    {
        $this->payment->club->recordMoneybirdError($exception->getMessage());
    }
}
