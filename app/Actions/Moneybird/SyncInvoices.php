<?php

namespace App\Actions\Moneybird;

use App\Models\Club;
use App\Models\Membership;
use App\Models\MoneybirdInvoice;
use App\Services\Moneybird\MoneybirdGateway;
use Illuminate\Support\Carbon;

class SyncInvoices
{
    /**
     * Moneybird caps a page at 100 records; the page cap keeps a single run
     * bounded so a first import cannot block a request indefinitely.
     */
    protected const PER_PAGE = 100;

    protected const MAX_PAGES = 25;

    public function __construct(protected MoneybirdGateway $gateway, protected RecordInvoice $recordInvoice) {}

    /**
     * Pull sales invoices from Moneybird into the local mirror. Only invoices
     * changed since the last sync are fetched, so repeated runs stay cheap.
     * Pass $full to re-import the whole administration.
     *
     * @return int The number of invoices stored or refreshed.
     */
    public function handle(Club $club, bool $full = false): int
    {
        if (! $club->hasMoneybirdConfigured()) {
            return 0;
        }

        $updatedAfter = $full ? null : $this->lastSyncedAt($club);
        $synced = 0;

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $invoices = $this->gateway->listInvoices($club, $updatedAfter, $page, self::PER_PAGE);

            if ($invoices === []) {
                break;
            }

            $membershipIds = $this->membershipIdsByContact($club, $invoices);

            foreach ($invoices as $invoice) {
                $this->recordInvoice->handle($club, $invoice, membershipIdsByContact: $membershipIds);
                $synced++;
            }

            if (count($invoices) < self::PER_PAGE) {
                break;
            }
        }

        return $synced;
    }

    /**
     * Rewind a minute to cover invoices changed while the previous sync ran.
     */
    protected function lastSyncedAt(Club $club): ?Carbon
    {
        $latest = MoneybirdInvoice::query()
            ->where('club_id', $club->id)
            ->max('moneybird_updated_at');

        return $latest === null ? null : Carbon::parse($latest)->subMinute();
    }

    /**
     * @param  list<array<string, mixed>>  $invoices
     * @return array<string, int>
     */
    protected function membershipIdsByContact(Club $club, array $invoices): array
    {
        $contactIds = collect($invoices)
            ->pluck('contact_id')
            ->filter()
            ->map(fn (mixed $contactId) => (string) $contactId)
            ->unique()
            ->all();

        if ($contactIds === []) {
            return [];
        }

        return Membership::query()
            ->where('club_id', $club->id)
            ->whereIn('moneybird_contact_id', $contactIds)
            ->pluck('id', 'moneybird_contact_id')
            ->all();
    }
}
