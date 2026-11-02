<?php

namespace App\Jobs;

use App\Actions\Moneybird\CreateCreditInvoiceForPayment;
use App\Jobs\Middleware\ReleaseWhenMoneybirdRateLimited;
use App\Models\Payment;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CreateMoneybirdCreditInvoice implements ShouldQueue
{
    use Queueable;

    /**
     * Real failures still give up after three attempts; Moneybird rate limits
     * only release the job and are bounded by retryUntil() instead.
     */
    public int $maxExceptions = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public Payment $payment) {}

    public function handle(CreateCreditInvoiceForPayment $createCreditInvoiceForPayment): void
    {
        $createCreditInvoiceForPayment->handle($this->payment->fresh());
    }

    /**
     * Large catch-ups can wait on Moneybird's rate limit for hours.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->plus(hours: 24);
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new ReleaseWhenMoneybirdRateLimited];
    }

    public function failed(Throwable $exception): void
    {
        $this->payment->club->recordMoneybirdError($exception->getMessage());
    }
}
