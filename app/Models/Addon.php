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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * A platform add-on clubs can subscribe to monthly. Priced and managed by superadmins.
 * A row without a key is a bundle: it unlocks every add-on in includedAddons.
 *
 * @property int $id
 * @property AddonKey|null $key null for a bundle
 * @property string $name
 * @property string|null $description
 * @property int $price_cents per month
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Addon> $includedAddons
 */
#[Fillable(['key', 'name', 'description', 'price_cents', 'is_active'])]
class Addon extends Model
{
    /** @use HasFactory<AddonFactory> */
    use HasFactory;

    public const string LANDING_CACHE_KEY = 'addons.landing';

    public const string LANDING_BUNDLES_CACHE_KEY = 'addons.landing-bundles';

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
     * The add-ons a bundle unlocks. Always empty for a single add-on.
     *
     * @return BelongsToMany<Addon, $this>
     */
    public function includedAddons(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'addon_bundle_items', 'bundle_id', 'addon_id')->orderBy('addons.id');
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
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function singles(Builder $query): void
    {
        $query->whereNotNull('key');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function bundles(Builder $query): void
    {
        $query->whereNull('key');
    }

    public function isBundle(): bool
    {
        return $this->key === null;
    }

    /**
     * The features a subscription to this add-on unlocks.
     *
     * @return list<AddonKey>
     */
    public function unlockedKeys(): array
    {
        if (! $this->isBundle()) {
            return [$this->key];
        }

        return $this->includedAddons->pluck('key')->filter()->values()->all();
    }

    /**
     * Whether a subscription to this add-on unlocks every feature of the other one,
     * so the other subscription becomes redundant once this one is active.
     */
    public function covers(self $other): bool
    {
        $keys = array_map(fn (AddonKey $key): string => $key->value, $this->unlockedKeys());
        $otherKeys = array_map(fn (AddonKey $key): string => $key->value, $other->unlockedKeys());

        return $otherKeys !== [] && array_diff($otherKeys, $keys) === [];
    }

    /**
     * What the included add-ons cost per month when bought one by one, at today's prices.
     */
    public function separatePriceCents(): int
    {
        return (int) $this->includedAddons->sum('price_cents');
    }

    public function icon(): string
    {
        return $this->key?->icon() ?? 'puzzle-piece';
    }

    /**
     * Make sure every AddonKey has a row, so superadmins can price it.
     * Bundles are listed after the single add-ons.
     *
     * @return Collection<int, self>
     */
    public static function syncFromKeys(): Collection
    {
        $existing = static::query()->singles()->pluck('key')->map(fn (AddonKey $key) => $key->value);

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

        return self::query()
            ->with('includedAddons')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (self $addon): int => $addon->isBundle() ? 1 : 0)
            ->values();
    }

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
            ->singles()
            ->get(['key', 'price_cents'])
            ->mapWithKeys(fn (self $addon) => [$addon->key->value => $addon->price_cents])
            ->all());
    }

    /**
     * The bundles for sale, cached as plain arrays for the public landing page.
     *
     * @return list<array{name: string, description: string|null, price_cents: int, separate_price_cents: int, included: list<string>}>
     */
    public static function landingBundles(): array
    {
        return Cache::remember(self::LANDING_BUNDLES_CACHE_KEY, now()->addHour(), fn (): array => static::query()
            ->active()
            ->bundles()
            ->with('includedAddons')
            ->orderBy('price_cents')
            ->get()
            ->map(fn (self $bundle): array => [
                'name' => $bundle->name,
                'description' => $bundle->description,
                'price_cents' => $bundle->price_cents,
                'separate_price_cents' => $bundle->separatePriceCents(),
                'included' => $bundle->includedAddons->pluck('name')->all(),
            ])
            ->all());
    }

    public static function forgetLandingCache(): void
    {
        Cache::forget(self::LANDING_CACHE_KEY);
        Cache::forget(self::LANDING_BUNDLES_CACHE_KEY);
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
