<?php

namespace App\Livewire\Clubs;

use App\Enums\AddonKey;
use App\Enums\MembershipStatus;
use App\Enums\MoneybirdInvoiceDelivery;
use App\Enums\PaymentStatus;
use App\Jobs\CreateMoneybirdInvoice;
use App\Jobs\SyncMoneybirdContact;
use App\Models\Club;
use App\Models\Payment;
use App\Services\Moneybird\MoneybirdGateway;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Connect a club to its Moneybird administration and configure how paid
 * payments are invoiced there.
 */
#[Title('Moneybird')]
class Moneybird extends Component
{
    public Club $club;

    public string $access_token = '';

    public string $administration_id = '';

    /** @var list<array{id: string, name: string}> */
    public array $administrations = [];

    public string $ledger_account_id = '';

    public string $tax_rate_id = '';

    public string $workflow_id = '';

    public string $document_style_id = '';

    public string $invoice_delivery = 'draft';

    public bool $sync_contacts = true;

    public function mount(Club $club): void
    {
        $this->authorize('update', $club);

        $this->club = $club;
        $this->access_token = $club->moneybird_access_token ?? '';
        $this->administration_id = $club->moneybird_administration_id ?? '';
        $this->ledger_account_id = $club->moneybird_ledger_account_id ?? '';
        $this->tax_rate_id = $club->moneybird_tax_rate_id ?? '';
        $this->workflow_id = $club->moneybird_workflow_id ?? '';
        $this->document_style_id = $club->moneybird_document_style_id ?? '';
        $this->invoice_delivery = $club->moneybird_invoice_delivery->value;
        $this->sync_contacts = $club->moneybird_sync_contacts;
    }

    /**
     * Step 1: validate the token by fetching the administrations it can access.
     */
    public function connect(MoneybirdGateway $gateway): void
    {
        $this->authorize('update', $this->club);
        $this->ensureAddon();

        $this->validate(['access_token' => ['required', 'string', 'min:20']]);

        try {
            $this->administrations = $gateway->listAdministrations($this->access_token);
        } catch (Throwable) {
            $this->addError('access_token', __('Moneybird rejected this token. Check that you copied the full personal API token.'));

            return;
        }

        if ($this->administrations === []) {
            $this->addError('access_token', __('This token has no access to any administration.'));

            return;
        }

        if (count($this->administrations) === 1) {
            $this->administration_id = $this->administrations[0]['id'];
            $this->saveConnection();

            return;
        }

        if (! collect($this->administrations)->contains('id', $this->administration_id)) {
            $this->administration_id = '';
        }
    }

    /**
     * Step 2 (only with multiple administrations): store the chosen one.
     */
    public function selectAdministration(): void
    {
        $this->authorize('update', $this->club);
        $this->ensureAddon();

        $this->validate([
            'administration_id' => ['required', Rule::in(collect($this->administrations)->pluck('id')->all())],
        ]);

        $this->saveConnection();
    }

    public function saveSettings(): void
    {
        $this->authorize('update', $this->club);
        $this->ensureAddon();

        abort_unless($this->club->hasMoneybirdConfigured(), 403);

        $validated = $this->validate([
            'ledger_account_id' => ['nullable', 'string', 'max:30'],
            'tax_rate_id' => ['nullable', 'string', 'max:30'],
            'workflow_id' => ['nullable', 'string', 'max:30'],
            'document_style_id' => ['nullable', 'string', 'max:30'],
            'invoice_delivery' => ['required', Rule::enum(MoneybirdInvoiceDelivery::class)],
            'sync_contacts' => ['boolean'],
        ]);

        $this->club->update([
            'moneybird_ledger_account_id' => $validated['ledger_account_id'] ?: null,
            'moneybird_tax_rate_id' => $validated['tax_rate_id'] ?: null,
            'moneybird_workflow_id' => $validated['workflow_id'] ?: null,
            'moneybird_document_style_id' => $validated['document_style_id'] ?: null,
            'moneybird_invoice_delivery' => $validated['invoice_delivery'],
            'moneybird_sync_contacts' => $validated['sync_contacts'],
        ]);

        Flux::toast(variant: 'success', text: __('Moneybird settings saved.'));
    }

    public function disconnect(): void
    {
        $this->authorize('update', $this->club);

        $this->club->update([
            'moneybird_access_token' => null,
            'moneybird_administration_id' => null,
            'moneybird_ledger_account_id' => null,
            'moneybird_tax_rate_id' => null,
            'moneybird_workflow_id' => null,
            'moneybird_document_style_id' => null,
            'moneybird_last_error' => null,
        ]);

        $this->forgetOptionCaches();
        $this->reset('access_token', 'administration_id', 'administrations', 'ledger_account_id', 'tax_rate_id', 'workflow_id', 'document_style_id');

        Flux::toast(variant: 'success', text: __('Moneybird disconnected.'));
    }

    public function refreshOptions(): void
    {
        $this->authorize('update', $this->club);

        $this->forgetOptionCaches();
        unset($this->ledgerAccounts, $this->taxRates, $this->workflows, $this->documentStyles);

        Flux::toast(variant: 'success', text: __('Moneybird options refreshed.'));
    }

    /**
     * Queue invoices for every paid payment that has none yet.
     */
    public function invoiceMissingPayments(): void
    {
        $this->authorize('update', $this->club);

        abort_unless($this->club->hasMoneybirdConfigured(), 403);

        $count = 0;

        $this->missingInvoicesQuery()->chunkById(200, function ($payments) use (&$count): void {
            foreach ($payments as $payment) {
                CreateMoneybirdInvoice::dispatch($payment);
                $count++;
            }
        });

        unset($this->missingInvoiceCount);

        Flux::toast(variant: 'success', text: trans_choice(':count invoice queued.|:count invoices queued.', $count));
    }

    /**
     * Queue a contact sync for every active member.
     */
    public function syncAllContacts(): void
    {
        $this->authorize('update', $this->club);

        abort_unless($this->club->hasMoneybirdConfigured(), 403);

        $count = 0;

        $this->club->memberships()
            ->where('status', MembershipStatus::Active)
            ->chunkById(200, function ($memberships) use (&$count): void {
                foreach ($memberships as $membership) {
                    SyncMoneybirdContact::dispatch($membership);
                    $count++;
                }
            });

        Flux::toast(variant: 'success', text: trans_choice(':count contact sync queued.|:count contact syncs queued.', $count));
    }

    #[Computed]
    public function hasAddon(): bool
    {
        return $this->club->hasAddon(AddonKey::Moneybird);
    }

    #[Computed]
    public function isConnected(): bool
    {
        return $this->club->hasMoneybirdConfigured();
    }

    #[Computed]
    public function missingInvoiceCount(): int
    {
        return $this->missingInvoicesQuery()->count();
    }

    #[Computed]
    public function unsyncedMemberCount(): int
    {
        return $this->club->memberships()
            ->where('status', MembershipStatus::Active)
            ->whereNull('moneybird_contact_id')
            ->count();
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    #[Computed]
    public function ledgerAccounts(): array
    {
        return $this->options('ledger_accounts', fn (MoneybirdGateway $gateway) => $gateway->listRevenueLedgerAccounts($this->club));
    }

    /**
     * @return list<array{id: string, name: string, percentage: string}>
     */
    #[Computed]
    public function taxRates(): array
    {
        return $this->options('tax_rates', fn (MoneybirdGateway $gateway) => $gateway->listSalesTaxRates($this->club));
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    #[Computed]
    public function workflows(): array
    {
        return $this->options('workflows', fn (MoneybirdGateway $gateway) => $gateway->listInvoiceWorkflows($this->club));
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    #[Computed]
    public function documentStyles(): array
    {
        return $this->options('document_styles', fn (MoneybirdGateway $gateway) => $gateway->listDocumentStyles($this->club));
    }

    protected function saveConnection(): void
    {
        $this->club->update([
            'moneybird_access_token' => $this->access_token,
            'moneybird_administration_id' => $this->administration_id,
            'moneybird_last_error' => null,
        ]);

        $this->forgetOptionCaches();
        unset($this->isConnected);

        Flux::toast(variant: 'success', text: __('Moneybird connected.'));
    }

    protected function ensureAddon(): void
    {
        abort_unless($this->club->hasAddon(AddonKey::Moneybird), 403);
    }

    /**
     * Moneybird reference data changes rarely; cache it briefly per club so the
     * settings page does not hit the API on every request.
     *
     * @template T of array
     *
     * @param  callable(MoneybirdGateway): T  $fetch
     * @return T
     */
    protected function options(string $kind, callable $fetch): array
    {
        if (! $this->club->hasMoneybirdConfigured()) {
            return [];
        }

        try {
            return Cache::remember($this->optionCacheKey($kind), now()->addMinutes(15), fn () => $fetch(app(MoneybirdGateway::class)));
        } catch (Throwable $exception) {
            $this->club->recordMoneybirdError($exception->getMessage());

            return [];
        }
    }

    protected function forgetOptionCaches(): void
    {
        foreach (['ledger_accounts', 'tax_rates', 'workflows', 'document_styles'] as $kind) {
            Cache::forget($this->optionCacheKey($kind));
        }
    }

    protected function optionCacheKey(string $kind): string
    {
        return "moneybird.{$this->club->id}.{$kind}";
    }

    protected function missingInvoicesQuery()
    {
        return Payment::query()
            ->where('club_id', $this->club->id)
            ->where('status', PaymentStatus::Paid)
            ->whereNull('moneybird_invoice_id');
    }

    public function render(): View
    {
        return view('livewire.clubs.moneybird');
    }
}
