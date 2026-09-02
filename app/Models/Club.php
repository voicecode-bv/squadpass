<?php

namespace App\Models;

use App\Enums\AddonKey;
use App\Enums\MembershipStatus;
use App\Enums\MoneybirdInvoiceDelivery;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Jobs\SyncMoneybirdContact;
use App\Services\Mollie\MollieAccount;
use Database\Factories\ClubFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 * @property string $currency
 * @property string|null $mollie_api_key
 * @property string|null $mollie_profile_id
 * @property string|null $platform_mollie_customer_id customer on the platform's Mollie account, for add-on billing
 * @property string|null $moneybird_access_token
 * @property string|null $moneybird_administration_id
 * @property string|null $moneybird_ledger_account_id
 * @property string|null $moneybird_tax_rate_id
 * @property string|null $moneybird_workflow_id
 * @property string|null $moneybird_document_style_id
 * @property MoneybirdInvoiceDelivery $moneybird_invoice_delivery
 * @property bool $moneybird_sync_contacts
 * @property string|null $moneybird_last_error
 * @property Carbon|null $moneybird_last_synced_at
 * @property int $next_membership_number
 * @property int $next_ticket_number
 * @property bool $self_signup
 * @property Carbon|null $onboarded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'timezone', 'currency', 'mollie_api_key', 'mollie_profile_id', 'platform_mollie_customer_id', 'moneybird_access_token', 'moneybird_administration_id',
    'moneybird_ledger_account_id', 'moneybird_tax_rate_id', 'moneybird_workflow_id', 'moneybird_document_style_id',
    'moneybird_invoice_delivery', 'moneybird_sync_contacts', 'moneybird_last_error', 'moneybird_last_synced_at',
    'self_signup', 'onboarded_at',
])]
#[Hidden(['mollie_api_key', 'moneybird_access_token'])]
class Club extends Model implements MollieAccount
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

    /**
     * Defaults that mirror the database, so freshly created clubs behave the
     * same before and after a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'moneybird_invoice_delivery' => 'draft',
        'moneybird_sync_contacts' => true,
    ];

    /**
     * Add-on keys that are currently unlocked, resolved once per instance.
     *
     * @var list<string>|null
     */
    protected ?array $activeAddonKeys = null;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mollie_api_key' => 'encrypted',
            'moneybird_access_token' => 'encrypted',
            'moneybird_invoice_delivery' => MoneybirdInvoiceDelivery::class,
            'moneybird_sync_contacts' => 'boolean',
            'moneybird_last_synced_at' => 'datetime',
            'next_membership_number' => 'integer',
            'next_ticket_number' => 'integer',
            'self_signup' => 'boolean',
            'onboarded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'club_staff')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasMany<Trainer, $this>
     */
    public function trainers(): HasMany
    {
        return $this->hasMany(Trainer::class);
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * @return HasMany<LessonOccurrence, $this>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(LessonOccurrence::class);
    }

    /**
     * @return HasMany<AddonSubscription, $this>
     */
    public function addonSubscriptions(): HasMany
    {
        return $this->hasMany(AddonSubscription::class);
    }

    /**
     * Whether the club currently has the given add-on unlocked: an active
     * subscription, or a cancelled one that is paid up until its end date.
     */
    public function hasAddon(AddonKey $key): bool
    {
        if ($this->activeAddonKeys === null) {
            $this->activeAddonKeys = AddonSubscription::query()
                ->where('addon_subscriptions.club_id', $this->id)
                ->where(function ($query): void {
                    $query->where('status', SubscriptionStatus::Active)
                        ->orWhere(function ($query): void {
                            $query->where('status', SubscriptionStatus::Cancelled)
                                ->whereDate('ends_at', '>=', today());
                        });
                })
                ->join('addons', 'addons.id', '=', 'addon_subscriptions.addon_id')
                ->pluck('addons.key')
                ->all();
        }

        return in_array($key->value, $this->activeAddonKeys, true);
    }

    public function forgetAddonCache(): void
    {
        $this->activeAddonKeys = null;
    }

    /**
     * Members can pay online once the Mollie add-on is active and a key is set.
     */
    public function acceptsOnlinePayments(): bool
    {
        return filled($this->mollie_api_key) && $this->hasAddon(AddonKey::Mollie);
    }

    public function hasMoneybirdConfigured(): bool
    {
        return filled($this->moneybird_access_token)
            && filled($this->moneybird_administration_id)
            && $this->hasAddon(AddonKey::Moneybird);
    }

    /**
     * Whether new and changed members are pushed to Moneybird automatically.
     */
    public function syncsMoneybirdContacts(): bool
    {
        return $this->hasMoneybirdConfigured() && $this->moneybird_sync_contacts;
    }

    /**
     * Link to a sales invoice in the club's Moneybird administration.
     */
    public function moneybirdInvoiceUrl(string $invoiceId): string
    {
        return "https://moneybird.com/{$this->moneybird_administration_id}/sales_invoices/{$invoiceId}";
    }

    public function recordMoneybirdSuccess(): void
    {
        $this->forceFill(['moneybird_last_error' => null, 'moneybird_last_synced_at' => now()])->saveQuietly();
    }

    public function recordMoneybirdError(string $message): void
    {
        $this->forceFill(['moneybird_last_error' => mb_substr($message, 0, 1000)])->saveQuietly();
    }

    public function mollieApiKey(): ?string
    {
        return $this->mollie_api_key ?: null;
    }

    public function mollieCurrency(): string
    {
        return $this->currency;
    }

    public function mollieAccountLabel(): string
    {
        return "club:{$this->slug}";
    }

    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null;
    }

    public function addStaff(User $user, StaffRole $role): void
    {
        $this->staff()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
    }

    /**
     * Add a user as a member, or reactivate their previous membership.
     */
    public function addMember(User $user): Membership
    {
        $membership = $this->memberships()->where('user_id', $user->id)->first();

        if ($membership) {
            $membership->update([
                'status' => MembershipStatus::Active,
                'ended_at' => null,
            ]);
        } else {
            $membership = $this->memberships()->create([
                'user_id' => $user->id,
                'membership_number' => $this->allocateMembershipNumber(),
                'status' => MembershipStatus::Active,
                'joined_at' => now()->toDateString(),
            ]);
        }

        if ($this->syncsMoneybirdContacts()) {
            SyncMoneybirdContact::dispatch($membership)->afterCommit();
        }

        return $membership;
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Claim the next sequential ticket number for this club atomically.
     */
    public function allocateTicketNumber(): int
    {
        return DB::transaction(function (): int {
            $club = self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            $number = $club->next_ticket_number;

            $club->forceFill(['next_ticket_number' => $number + 1])->save();

            return $number;
        });
    }

    /**
     * Claim the next sequential membership number for this club atomically.
     */
    public function allocateMembershipNumber(): int
    {
        return DB::transaction(function (): int {
            $club = self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            $number = $club->next_membership_number;

            $club->forceFill(['next_membership_number' => $number + 1])->save();

            return $number;
        });
    }
}
