<?php

namespace App\Console\Commands;

use App\Actions\Moneybird\SyncInvoices;
use App\Models\Club;
use Illuminate\Console\Command;
use Illuminate\Support\LazyCollection;
use Throwable;

class SyncMoneybirdInvoices extends Command
{
    protected $signature = 'moneybird:sync-invoices {club? : Slug of a single club} {--full : Re-import every invoice instead of only the changed ones}';

    protected $description = 'Mirror Moneybird sales invoices locally so they can be listed and filtered';

    public function handle(SyncInvoices $syncInvoices): int
    {
        $synced = 0;
        $failed = 0;

        $this->clubs()->each(function (Club $club) use ($syncInvoices, &$synced, &$failed) {
            try {
                $synced += $syncInvoices->handle($club, (bool) $this->option('full'));
            } catch (Throwable $exception) {
                $failed++;

                report($exception);
            }
        });

        $this->info("Synced {$synced} invoices".($failed > 0 ? ", {$failed} clubs failed." : '.'));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return LazyCollection<int, Club>
     */
    protected function clubs(): LazyCollection
    {
        return Club::query()
            ->whereNotNull('moneybird_access_token')
            ->whereNotNull('moneybird_administration_id')
            ->when($this->argument('club'), fn ($query, $slug) => $query->where('slug', $slug))
            ->lazyById(50);
    }
}
