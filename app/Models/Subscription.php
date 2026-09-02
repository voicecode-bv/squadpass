<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property int $user_id
 * @property int $subscription_plan_id
 * @property SubscriptionStatus $status
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $next_billing_at
 * @property int|null $credits_remaining null means unlimited
 * @property string|null $mollie_mandate_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'club_id', 'user_id', 'subscription_plan_id', 'status',
    'starts_at', 'ends_at', 'cancelled_at', 'next_billing_at', 'credits_remaining', 'mollie_mandate_id',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'cancelled_at' => 'datetime',
            'next_billing_at' => 'date',
            'credits_remaining' => 'integer',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * The subscription that currently grants a user access at a club, if any.
     */
    public static function currentFor(int $clubId, int $userId): ?self
    {
        return static::query()
            ->where('club_id', $clubId)
            ->where('user_id', $userId)
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Cancelled])
            ->get()
            ->first(fn (self $subscription) => $subscription->isCurrent());
    }

    public function isUnlimited(): bool
    {
        return $this->credits_remaining === null;
    }

    /**
     * Atomically consume credits. Unlimited subscriptions always succeed.
     * Returns false when not enough credits remain.
     */
    public function consumeCredits(int $count): bool
    {
        if ($this->isUnlimited() || $count <= 0) {
            return true;
        }

        $consumed = static::query()
            ->whereKey($this->id)
            ->where('credits_remaining', '>=', $count)
            ->decrement('credits_remaining', $count) === 1;

        if ($consumed) {
            $this->credits_remaining -= $count;
        }

        return $consumed;
    }

    public function refundCredits(int $count): void
    {
        if ($this->isUnlimited() || $count <= 0) {
            return;
        }

        static::query()->whereKey($this->id)->increment('credits_remaining', $count);

        $this->credits_remaining += $count;
    }

    /**
     * Whether this subscription currently grants access:
     * active, or cancelled but paid up until its end date.
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
     * Whether a new subscription may be started alongside this one.
     */
    public function blocksNewSubscription(): bool
    {
        return in_array($this->status, [
            SubscriptionStatus::Pending,
            SubscriptionStatus::Active,
            SubscriptionStatus::PastDue,
        ], true) || $this->isCurrent();
    }
}
