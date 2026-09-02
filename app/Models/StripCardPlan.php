<?php

namespace App\Models;

use Database\Factories\StripCardPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property string $name
 * @property int $strips
 * @property int $price_cents
 * @property int|null $validity_months
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['club_id', 'name', 'strips', 'price_cents', 'validity_months', 'active'])]
class StripCardPlan extends Model
{
    /** @use HasFactory<StripCardPlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'strips' => 'integer',
            'price_cents' => 'integer',
            'validity_months' => 'integer',
            'active' => 'boolean',
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
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('active', true);
    }

    public function formattedPrice(): string
    {
        return '€ '.number_format($this->price_cents / 100, 2, ',', '.');
    }
}
