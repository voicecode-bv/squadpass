<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Jobs\CreateMoneybirdInvoice;
use App\Models\Club;
use App\Models\Payment;
use Illuminate\Console\Command;

class InvoiceMissingMoneybirdPayments extends Command
{
    protected $signature = 'moneybird:invoice-payments {club? : Club slug; all Moneybird clubs when omitted}';

    protected $description = 'Queue Moneybird invoices for paid payments that have none yet';

    public function handle(): int
    {
        $clubs = $this->argument('club')
            ? Club::query()->where('slug', $this->argument('club'))->get()
            : Club::query()->whereNotNull('moneybird_access_token')->whereNotNull('moneybird_administration_id')->get();

        $queued = 0;

        foreach ($clubs->filter(fn (Club $club) => $club->hasMoneybirdConfigured()) as $club) {
            Payment::query()
                ->where('club_id', $club->id)
                ->where('status', PaymentStatus::Paid)
                ->whereNull('moneybird_invoice_id')
                ->chunkById(200, function ($payments) use (&$queued): void {
                    foreach ($payments as $payment) {
                        CreateMoneybirdInvoice::dispatch($payment);
                        $queued++;
                    }
                });
        }

        $this->info("Queued {$queued} Moneybird invoices.");

        return self::SUCCESS;
    }
}
