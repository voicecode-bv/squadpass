<?php

namespace App\Livewire\Invoices;

use App\Actions\Moneybird\CreateCreditInvoice;
use App\Actions\Moneybird\SyncInvoices;
use App\Enums\MoneybirdInvoiceState;
use App\Exceptions\MoneybirdException;
use App\Models\Club;
use App\Models\MoneybirdInvoice;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Invoices')]
class Index extends Component
{
    use WithPagination;

    public Club $club;

    /** Invoice number or reference. */
    #[Url]
    public string $search = '';

    /** Member name, email address or membership number. */
    #[Url]
    public string $member = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $until = '';

    #[Url]
    public string $minAmount = '';

    #[Url]
    public string $maxAmount = '';

    #[Url]
    public string $state = '';

    /** One of: all, invoices, credits. */
    #[Url]
    public string $type = 'all';

    public ?int $selectedInvoiceId = null;

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    public function updated(string $property): void
    {
        if ($property !== 'selectedInvoiceId') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'member', 'from', 'until', 'minAmount', 'maxAmount', 'state', 'type']);
        $this->resetPage();
    }

    /**
     * Pull invoices created or changed in Moneybird into the local overview.
     */
    public function sync(SyncInvoices $syncInvoices): void
    {
        $this->authorize('manage', $this->club);

        if (! $this->club->hasMoneybirdConfigured()) {
            Flux::toast(variant: 'danger', text: __('This club has no Moneybird administration connected.'));

            return;
        }

        $synced = $syncInvoices->handle($this->club);

        unset($this->invoices, $this->totals);

        Flux::toast(variant: 'success', text: trans_choice(':count invoice synced from Moneybird.|:count invoices synced from Moneybird.', $synced, ['count' => $synced]));
    }

    public function showInvoice(int $invoiceId): void
    {
        $this->authorize('manage', $this->club);

        $this->selectedInvoiceId = $this->scopedQuery()->whereKey($invoiceId)->value('id');

        Flux::modal('invoice-detail')->show();
    }

    /**
     * Duplicate an invoice into a draft credit invoice in Moneybird.
     */
    public function createCreditInvoice(int $invoiceId, CreateCreditInvoice $createCreditInvoice): void
    {
        $this->authorize('manage', $this->club);

        $invoice = $this->scopedQuery()->findOrFail($invoiceId);

        try {
            $creditInvoice = $createCreditInvoice->handle($invoice);
        } catch (MoneybirdException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->invoices, $this->totals, $this->selectedInvoice);

        Flux::modal('invoice-detail')->close();

        Flux::toast(variant: 'success', text: __('Credit invoice created as a draft in Moneybird (:amount).', [
            'amount' => $creditInvoice->formattedTotal(),
        ]));
    }

    /**
     * @return LengthAwarePaginator<int, MoneybirdInvoice>
     */
    #[Computed]
    public function invoices(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with(['membership.user', 'creditedInvoice:id,invoice_number,moneybird_id'])
            ->withCount('creditInvoices')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(25);
    }

    /**
     * Count and net total of everything matching the current filters, not just
     * the page in view.
     *
     * @return array{count: int, total_cents: int}
     */
    #[Computed]
    public function totals(): array
    {
        /** @var object{invoice_count: int, total_cents: int|null} $totals */
        $totals = $this->filteredQuery()
            ->toBase()
            ->selectRaw('count(*) as invoice_count, sum(total_price_incl_tax_cents) as total_cents')
            ->first();

        return ['count' => (int) $totals->invoice_count, 'total_cents' => (int) $totals->total_cents];
    }

    #[Computed]
    public function selectedInvoice(): ?MoneybirdInvoice
    {
        if ($this->selectedInvoiceId === null) {
            return null;
        }

        return $this->scopedQuery()
            ->with(['membership.user', 'creditedInvoice', 'creditInvoices'])
            ->find($this->selectedInvoiceId);
    }

    /**
     * @return list<MoneybirdInvoiceState>
     */
    #[Computed]
    public function states(): array
    {
        return MoneybirdInvoiceState::filterable();
    }

    /**
     * @return Builder<MoneybirdInvoice>
     */
    protected function scopedQuery(): Builder
    {
        return MoneybirdInvoice::query()->where('club_id', $this->club->id);
    }

    /**
     * @return Builder<MoneybirdInvoice>
     */
    protected function filteredQuery(): Builder
    {
        return $this->scopedQuery()
            ->when($this->search !== '', function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query->where('invoice_number', 'like', $this->search.'%')
                        ->orWhere('reference', 'like', $this->search.'%');
                });
            })
            ->when($this->member !== '', function (Builder $query) {
                $query->whereHas('membership', function (Builder $membership) {
                    $membership->when(ctype_digit($this->member), fn (Builder $query) => $query->where('membership_number', (int) $this->member))
                        ->when(! ctype_digit($this->member), fn (Builder $query) => $query->whereHas('user', function (Builder $user) {
                            $user->where('name', 'like', $this->member.'%')
                                ->orWhere('email', 'like', $this->member.'%');
                        }));
                });
            })
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('invoice_date', '>=', $this->from))
            ->when($this->until !== '', fn (Builder $query) => $query->whereDate('invoice_date', '<=', $this->until))
            ->when($this->amountCents($this->minAmount) !== null || $this->amountCents($this->maxAmount) !== null, function (Builder $query) {
                $min = $this->amountCents($this->minAmount) ?? 0;
                $max = $this->amountCents($this->maxAmount) ?? PHP_INT_MAX;

                // Credit invoices are stored negative; match them on the amount
                // that was credited so a range covers both sides.
                $query->where(function (Builder $query) use ($min, $max) {
                    $query->whereBetween('total_price_incl_tax_cents', [$min, $max])
                        ->orWhereBetween('total_price_incl_tax_cents', [-$max, -$min]);
                });
            })
            ->when($this->state !== '', fn (Builder $query) => $query->where('state', $this->state))
            ->when($this->type === 'invoices', fn (Builder $query) => $query->where('is_credit_invoice', false))
            ->when($this->type === 'credits', fn (Builder $query) => $query->where('is_credit_invoice', true));
    }

    protected function amountCents(string $amount): ?int
    {
        $amount = str_replace(',', '.', trim($amount));

        return is_numeric($amount) ? (int) round(((float) $amount) * 100) : null;
    }

    public function render(): View
    {
        return view('livewire.invoices.index');
    }
}
