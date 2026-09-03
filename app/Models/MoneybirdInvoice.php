<?php

namespace App\Models;

use App\Enums\MoneybirdInvoiceState;
use Database\Factories\MoneybirdInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A local mirror of a Moneybird sales invoice, so the overview can be filtered
 * and paginated in the database instead of through the Moneybird API.
 *
 * @property int $id
 * @property int $club_id
 * @property int|null $membership_id
 * @property int|null $payment_id
 * @property int|null $credited_invoice_id
 * @property string $moneybird_id
 * @property string|null $moneybird_contact_id
 * @property string|null $invoice_number
 * @property string|null $reference
 * @property MoneybirdInvoiceState $state
 * @property Carbon|null $invoice_date
 * @property int $total_price_incl_tax_cents
 * @property string $currency
 * @property bool $is_credit_invoice
 * @property list<array{description: string, amount: string|null, price_cents: int, total_price_incl_tax_cents: int}>|null $lines
 * @property Carbon|null $moneybird_updated_at
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'club_id', 'membership_id', 'payment_id', 'credited_invoice_id', 'moneybird_id', 'moneybird_contact_id',
    'invoice_number', 'reference', 'state', 'invoice_date', 'total_price_incl_tax_cents', 'currency',
    'is_credit_invoice', 'lines', 'moneybird_updated_at', 'synced_at',
])]
class MoneybirdInvoice extends Model
{
    /** @use HasFactory<MoneybirdInvoiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => MoneybirdInvoiceState::class,
            'invoice_date' => 'date',
            'total_price_incl_tax_cents' => 'integer',
            'is_credit_invoice' => 'boolean',
            'lines' => 'array',
            'moneybird_updated_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<Membership, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * The invoice this credit invoice was created from.
     *
     * @return BelongsTo<MoneybirdInvoice, $this>
     */
    public function creditedInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credited_invoice_id');
    }

    /**
     * Credit invoices created from this invoice.
     *
     * @return HasMany<MoneybirdInvoice, $this>
     */
    public function creditInvoices(): HasMany
    {
        return $this->hasMany(self::class, 'credited_invoice_id');
    }

    /**
     * Only a regular invoice that has not been credited yet can be turned into
     * a credit invoice. Uses an eager loaded count or relation when the caller
     * provided one, so a listing does not query per row.
     */
    public function isCreditable(): bool
    {
        if ($this->is_credit_invoice) {
            return false;
        }

        if (array_key_exists('credit_invoices_count', $this->attributes)) {
            return (int) $this->attributes['credit_invoices_count'] === 0;
        }

        if ($this->relationLoaded('creditInvoices')) {
            return $this->creditInvoices->isEmpty();
        }

        return $this->creditInvoices()->doesntExist();
    }

    public function formattedTotal(): string
    {
        return '€ '.number_format($this->total_price_incl_tax_cents / 100, 2, ',', '.');
    }

    public function moneybirdUrl(): ?string
    {
        if (! filled($this->club->moneybird_administration_id)) {
            return null;
        }

        return "https://moneybird.com/{$this->club->moneybird_administration_id}/sales_invoices/{$this->moneybird_id}";
    }
}
