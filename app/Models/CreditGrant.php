<?php

namespace App\Models;

use Database\Factories\CreditGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A batch of lesson credits with its own expiry date. The subscription's
 * credits_remaining is the cached sum of the remainders of its unexpired grants.
 *
 * @property int $id
 * @property int $club_id
 * @property int $user_id
 * @property int $subscription_id
 * @property int $amount
 * @property int $remaining
 * @property Carbon|null $expires_at null means the credits never expire
 * @property Carbon|null $expiry_warned_at set once the member was warned about the upcoming expiry
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['club_id', 'user_id', 'subscription_id', 'amount', 'remaining', 'expires_at', 'expiry_warned_at'])]
class CreditGrant extends Model
{
    /** @use HasFactory<CreditGrantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'remaining' => 'integer',
            'expires_at' => 'datetime',
            'expiry_warned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(now());
    }

    /**
     * Grants whose credits can still be spent.
     *
     * @param  Builder<self>  $query
     */
    protected function scopeUnexpired(Builder $query): void
    {
        $query->where(fn (Builder $unexpired) => $unexpired->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
