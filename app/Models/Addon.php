<?php

namespace App\Models;

use App\Enums\AddonKey;
use Database\Factories\AddonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * A platform add-on clubs can subscribe to monthly. Priced and managed by superadmins.
 *
 * @property int $id
 * @property AddonKey $key
 * @property string $name
 * @property string|null $description
 * @property int $price_cents per month
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'name', 'description', 'price_cents', 'is_active'])]
class Addon extends Model
{
    /** @use HasFactory<AddonFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => AddonKey::class,
            'price_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<AddonSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(AddonSubscription::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Make sure every AddonKey has a row, so superadmins can price it.
     *
     * @return Collection<int, self>
     */
    public static function syncFromKeys(): Collection
    {
        $existing = static::query()->pluck('key')->map(fn (AddonKey $key) => $key->value);

        foreach (AddonKey::cases() as $key) {
            if (! $existing->contains($key->value)) {
                static::create([
                    'key' => $key,
                    'name' => $key->defaultName(),
                    'description' => $key->defaultDescription(),
                    'price_cents' => 0,
                    'is_active' => false,
                ]);
            }
        }

        return static::query()->orderBy('id')->get();
    }

    public const string LANDING_CACHE_KEY = 'addons.landing';

    /**
     * Monthly prices in cents of the add-ons for sale, keyed by AddonKey value.
     * Cached as a plain array (never models) for the public landing page.
     *
     * @return array<string, int>
     */
    public static function landingPrices(): array
    {
        return Cache::remember(self::LANDING_CACHE_KEY, now()->addHour(), fn (): array => static::query()
            ->active()
            ->get(['key', 'price_cents'])
            ->mapWithKeys(fn (self $addon) => [$addon->key->value => $addon->price_cents])
            ->all());
    }

    public static function formatCents(int $cents): string
    {
        return '€ '.number_format($cents / 100, 2, ',', '.');
    }

    public function formattedPrice(): string
    {
        return self::formatCents($this->price_cents);
    }
}
