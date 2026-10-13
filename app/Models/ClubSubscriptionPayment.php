<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\ClubSubscriptionPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Mollie payment on the platform account for a club's SquadPass plan.
 *
 * @property int $id
 * @property int $club_id
 * @property int $club_subscription_id
 * @property string $mollie_payment_id
 * @property string $purpose first|recurring
 * @property PaymentStatus $status
 * @property int $amount_cents
 * @property string $currency
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'club_id', 'club_subscription_id', 'mollie_payment_id', 'purpose',
    'status', 'amount_cents', 'currency', 'paid_at',
])]
class ClubSubscriptionPayment extends Model
{
    /** @use HasFactory<ClubSubscriptionPaymentFactory> */
    use HasFactory;

    public const string PURPOSE_FIRST = 'first';

    public const string PURPOSE_RECURRING = 'recurring';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
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
     * @return BelongsTo<ClubSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ClubSubscription::class, 'club_subscription_id');
    }

    public function formattedAmount(): string
    {
        return Addon::formatCents($this->amount_cents);
    }
}
