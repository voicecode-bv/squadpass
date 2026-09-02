<?php

namespace App\Models;

use Database\Factories\StripCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property int $user_id
 * @property int $strip_card_plan_id
 * @property int|null $payment_id
 * @property int $strips_total
 * @property int $strips_remaining
 * @property Carbon $purchased_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['club_id', 'user_id', 'strip_card_plan_id', 'payment_id', 'strips_total', 'strips_remaining', 'purchased_at', 'expires_at'])]
class StripCard extends Model
{
    /** @use HasFactory<StripCardFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'strips_total' => 'integer',
            'strips_remaining' => 'integer',
            'purchased_at' => 'date',
            'expires_at' => 'date',
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
     * @return BelongsTo<StripCardPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(StripCardPlan::class, 'strip_card_plan_id');
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isUsable(): bool
    {
        return $this->strips_remaining > 0
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Atomically redeem one strip. Returns false when the card is empty or expired.
     */
    public function redeemStrip(): bool
    {
        $redeemed = static::query()
            ->whereKey($this->id)
            ->where('strips_remaining', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>=', today());
            })
            ->decrement('strips_remaining') === 1;

        if ($redeemed) {
            $this->strips_remaining--;
        }

        return $redeemed;
    }
}
