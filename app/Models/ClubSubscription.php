<?php

namespace App\Models;

use App\Enums\ClubPlan;
use App\Enums\SubscriptionStatus;
use Database\Factories\ClubSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A club's monthly SquadPass plan, billed via the platform's Mollie account.
 * Manually activated plans (Enterprise) have no mandate and are never charged.
 *
 * @property int $id
 * @property int $club_id
 * @property int|null $user_id staff member who took out the plan
 * @property ClubPlan $plan
 * @property SubscriptionStatus $status
 * @property int $price_cents monthly price locked in at purchase
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $next_billing_at
 * @property string|null $mollie_mandate_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'club_id', 'user_id', 'plan', 'status', 'price_cents',
    'starts_at', 'ends_at', 'cancelled_at', 'next_billing_at', 'mollie_mandate_id',
])]
class ClubSubscription extends Model
{
    /** @use HasFactory<ClubSubscriptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => ClubPlan::class,
            'status' => SubscriptionStatus::class,
            'price_cents' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'cancelled_at' => 'datetime',
            'next_billing_at' => 'date',
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
     * @return BelongsTo<User, $this>
     */
    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<ClubSubscriptionPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ClubSubscriptionPayment::class);
    }

    /**
     * Whether the plan is in force: active, or cancelled but paid up until its end date.
     */
    public function isCurrent(): bool
    {
        return match ($this->status) {
            SubscriptionStatus::Active => true,
            SubscriptionStatus::Cancelled => $this->ends_at !== null && $this->ends_at->gte(today()),
            default => false,
        };
    }

    /**
     * Whether this subscription stands in the way of starting a new one.
     */
    public function blocksNewSubscription(): bool
    {
        return in_array($this->status, [
            SubscriptionStatus::Pending,
            SubscriptionStatus::Active,
            SubscriptionStatus::PastDue,
        ], true) || $this->isCurrent();
    }

    public function isBilledManually(): bool
    {
        return $this->mollie_mandate_id === null;
    }

    public function formattedPrice(): string
    {
        return Addon::formatCents($this->price_cents);
    }
}
