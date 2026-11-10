<?php

namespace App\Models;

use App\Enums\AddonKey;
use App\Enums\ClubPlan;
use App\Enums\MembershipStatus;
use App\Enums\MoneybirdInvoiceDelivery;
use App\Enums\PaymentStatus;
use App\Enums\StaffPermission;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Exceptions\ClubPlanException;
use App\Jobs\SyncMoneybirdContact;
use App\Services\BrandPalette;
use App\Services\Mollie\MollieAccount;
use Carbon\CarbonInterface;
use Database\Factories\ClubFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $photo_path
 * @property string|null $logo_path light-mode logo, shown above the navigation and at the top of the club's emails
 * @property string|null $logo_dark_path dark-mode logo above the navigation; falls back to the light logo
 * @property string|null $brand_color hex colour that replaces SquadPass blue in the app and emails
 * @property string $timezone
 * @property string $currency
 * @property string|null $mollie_api_key
 * @property string|null $mollie_profile_id
 * @property string|null $mollie_profile_name name of the Mollie profile the API key belongs to, fetched when the key is saved
 * @property string|null $mollie_last_error message of the last failed Mollie request, cleared by the next successful one
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
 * @property Carbon|null $moneybird_setup_completed_at set when the owner finishes the Moneybird setup wizard
 * @property int $next_membership_number
 * @property int $next_ticket_number
 * @property bool $self_signup
 * @property bool $trial_lessons_enabled visitors can book a trial lesson on the public trial page
 * @property int $cancellation_deadline_hours members cancelling later than this many hours before a lesson keep no credit
 * @property int|null $credit_expiry_days days after which granted credits expire; null means never
 * @property int|null $lesson_reminder_hours members get a reminder email this many hours before a lesson; null means off
 * @property bool $is_demo demo club: every add-on unlocked, integrations managed by superadmins only
 * @property Carbon|null $demo_expires_at set on a visitor's demo sandbox, which is deleted once it passes
 * @property Carbon|null $onboarded_at
 * @property CarbonInterface|null $trial_ends_at last day a club without a plan may still add members
 * @property int|null $founding_discount_percent lasting discount on every plan, claimed in the opening offer
 * @property Carbon|null $founding_claimed_at when the club claimed its founding spot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'photo_path', 'logo_path', 'logo_dark_path', 'brand_color', 'timezone', 'currency', 'mollie_api_key', 'mollie_profile_id', 'mollie_profile_name', 'mollie_last_error', 'platform_mollie_customer_id', 'moneybird_access_token', 'moneybird_administration_id',
    'moneybird_ledger_account_id', 'moneybird_tax_rate_id', 'moneybird_workflow_id', 'moneybird_document_style_id',
    'moneybird_invoice_delivery', 'moneybird_sync_contacts', 'moneybird_last_error', 'moneybird_last_synced_at', 'moneybird_setup_completed_at',
    'self_signup', 'trial_lessons_enabled', 'cancellation_deadline_hours', 'credit_expiry_days', 'lesson_reminder_hours', 'onboarded_at', 'trial_ends_at',
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
        'is_demo' => false,
        'trial_lessons_enabled' => false,
        'cancellation_deadline_hours' => 3,
        'credit_expiry_days' => 30,
    ];

    /**
     * Add-on keys that are currently unlocked, resolved once per instance.
     *
     * @var list<string>|null
     */
    protected ?array $activeAddonKeys = null;

    /**
     * The plan in force, resolved once per instance.
     */
    protected ?ClubSubscription $currentSubscription = null;

    protected bool $currentSubscriptionResolved = false;

    protected static function booted(): void
    {
        // Every new club starts with a trial, however it is created.
        static::creating(function (Club $club): void {
            $club->trial_ends_at ??= today()->addDays(ClubPlan::TRIAL_DAYS);
        });
    }

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
            'moneybird_setup_completed_at' => 'datetime',
            'next_membership_number' => 'integer',
            'next_ticket_number' => 'integer',
            'self_signup' => 'boolean',
            'trial_lessons_enabled' => 'boolean',
            'is_demo' => 'boolean',
            'cancellation_deadline_hours' => 'integer',
            'credit_expiry_days' => 'integer',
            'lesson_reminder_hours' => 'integer',
            'demo_expires_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'trial_ends_at' => 'date',
            'founding_discount_percent' => 'integer',
            'founding_claimed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'club_staff')
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function owners(): BelongsToMany
    {
        return $this->staff()->wherePivot('role', StaffRole::Owner->value);
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasMany<Form, $this>
     */
    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
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
     * @return HasMany<NewsPost, $this>
     */
    public function newsPosts(): HasMany
    {
        return $this->hasMany(NewsPost::class);
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
     * A bundle subscription unlocks every add-on included in the bundle.
     */
    public function hasAddon(AddonKey $key): bool
    {
        if ($this->is_demo) {
            return true;
        }

        return in_array($key->value, $this->unlockedAddonKeys(), true);
    }

    /**
     * Values of every AddonKey the club's current subscriptions unlock.
     *
     * @return list<string>
     */
    public function unlockedAddonKeys(): array
    {
        if ($this->activeAddonKeys === null) {
            $this->activeAddonKeys = array_values(AddonSubscription::query()
                ->where('addon_subscriptions.club_id', $this->id)
                ->where(function ($query): void {
                    $query->where('status', SubscriptionStatus::Active)
                        ->orWhere(function ($query): void {
                            $query->where('status', SubscriptionStatus::Cancelled)
                                ->whereDate('ends_at', '>=', today());
                        });
                })
                ->join('addons', 'addons.id', '=', 'addon_subscriptions.addon_id')
                ->leftJoin('addon_bundle_items', 'addon_bundle_items.bundle_id', '=', 'addons.id')
                ->leftJoin('addons as included_addons', 'included_addons.id', '=', 'addon_bundle_items.addon_id')
                ->selectRaw('coalesce(included_addons.key, addons.key) as unlocked_key')
                ->pluck('unlocked_key')
                ->filter()
                ->map(fn (mixed $addonKey): string => (string) $addonKey)
                ->unique()
                ->values()
                ->all());
        }

        return $this->activeAddonKeys;
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

    /**
     * Whether the club's Mollie key processes real payments.
     */
    public function mollieIsLive(): bool
    {
        return str_starts_with((string) $this->mollie_api_key, 'live_');
    }

    /**
     * Recurring subscription charges that could not be collected in the past 30 days.
     */
    public function recentFailedChargeCount(): int
    {
        return Payment::query()
            ->where('club_id', $this->id)
            ->where('metadata->purpose', 'subscription_recurring')
            ->whereIn('status', [PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Canceled])
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();
    }

    public function recordMollieError(string $message): void
    {
        $this->forceFill(['mollie_last_error' => mb_substr($message, 0, 1000)])->saveQuietly();
    }

    /**
     * Clears a recorded error. Only writes when there is one, so successful
     * payment requests do not cost an extra query each.
     */
    public function recordMollieSuccess(): void
    {
        if ($this->mollie_last_error !== null) {
            $this->forceFill(['mollie_last_error' => null])->saveQuietly();
        }
    }

    public function mollieCurrency(): string
    {
        return $this->currency;
    }

    public function mollieAccountLabel(): string
    {
        return "club:{$this->slug}";
    }

    /**
     * Public URL of the uploaded club photo, or null when none is set.
     */
    public function photoUrl(): ?string
    {
        return $this->photo_path
            ? Storage::disk('public')->url($this->photo_path)
            : null;
    }

    /**
     * Public URL of the light-mode logo (also used in emails), or null when none is set.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }

    /**
     * Public URL of the dark-mode logo, falling back to the light logo.
     */
    public function darkLogoUrl(): ?string
    {
        return $this->logo_dark_path
            ? Storage::disk('public')->url($this->logo_dark_path)
            : $this->logoUrl();
    }

    /**
     * The 50–950 palette replacing SquadPass blue, or null when the club keeps blue.
     *
     * @return array<int, string>|null
     */
    public function brandPalette(): ?array
    {
        return BrandPalette::isValidHex($this->brand_color)
            ? BrandPalette::fromHex($this->brand_color)
            : null;
    }

    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null;
    }

    /**
     * Add a user as staff, or change their role. A staff member without explicit
     * permissions gets every area; owners always have every area.
     *
     * @param  list<StaffPermission>|null  $permissions
     */
    public function addStaff(User $user, StaffRole $role, ?array $permissions = null): void
    {
        $permissions = $role === StaffRole::Owner ? null : ($permissions ?? StaffPermission::cases());

        $this->staff()->syncWithoutDetaching([$user->id => [
            'role' => $role->value,
            'permissions' => $permissions === null ? null : json_encode(array_map(fn (StaffPermission $permission): string => $permission->value, $permissions)),
        ]]);

        $user->forgetStaffAccess();
    }

    /**
     * Add a user as a member, or reactivate their previous membership. Requires a
     * trial or plan with room for another active member.
     *
     * @throws ClubPlanException
     */
    public function addMember(User $user): Membership
    {
        $membership = $this->memberships()->where('user_id', $user->id)->first();

        if ($membership?->status === MembershipStatus::Active) {
            return $membership;
        }

        $membership = DB::transaction(function () use ($user, $membership): Membership {
            // Lock the club row so concurrent sign-ups cannot slip past the member limit.
            self::query()->whereKey($this->id)->lockForUpdate()->first();

            $this->ensureCanAddMembers();

            if ($membership) {
                $membership->update([
                    'status' => MembershipStatus::Active,
                    'ended_at' => null,
                    // A converted trial member is a member from today; trial_started_at keeps the trial.
                    ...($membership->status === MembershipStatus::Trial ? ['joined_at' => now()->toDateString()] : []),
                ]);

                return $membership;
            }

            return $this->memberships()->create([
                'user_id' => $user->id,
                'membership_number' => $this->allocateMembershipNumber(),
                'status' => MembershipStatus::Active,
                'joined_at' => now()->toDateString(),
            ]);
        });

        if ($this->syncsMoneybirdContacts()) {
            SyncMoneybirdContact::dispatch($membership)->afterCommit();
        }

        return $membership;
    }

    /**
     * @return HasMany<ClubSubscription, $this>
     */
    public function clubSubscriptions(): HasMany
    {
        return $this->hasMany(ClubSubscription::class);
    }

    /**
     * The SquadPass plan in force: active, or cancelled but paid up until its end date.
     */
    public function currentSubscription(): ?ClubSubscription
    {
        if (! $this->currentSubscriptionResolved) {
            $this->currentSubscription = $this->clubSubscriptions()
                ->where(function ($query): void {
                    $query->where('status', SubscriptionStatus::Active)
                        ->orWhere(function ($query): void {
                            $query->where('status', SubscriptionStatus::Cancelled)
                                ->whereDate('ends_at', '>=', today());
                        });
                })
                ->latest('id')
                ->first();

            $this->currentSubscriptionResolved = true;
        }

        return $this->currentSubscription;
    }

    /**
     * Whether the club claimed a spot in the opening offer and keeps its founding price.
     */
    public function isFoundingMember(): bool
    {
        return $this->founding_claimed_at !== null && $this->founding_discount_percent !== null;
    }

    /**
     * Monthly price of a plan for this club: the plan price minus its founding
     * discount, if any. Null when the plan is priced on request.
     */
    public function planPriceCents(ClubPlan $plan): ?int
    {
        $priceCents = $plan->priceCents();

        if ($priceCents === null || ! $this->isFoundingMember()) {
            return $priceCents;
        }

        return LaunchOffer::applyDiscount($priceCents, (int) $this->founding_discount_percent);
    }

    public function forgetSubscriptionCache(): void
    {
        $this->currentSubscription = null;
        $this->currentSubscriptionResolved = false;
    }

    /**
     * A temporary demo club for one landing page visitor. Nothing in it may
     * reach the outside world: no mail, no payments, no integrations.
     */
    public function isDemoSandbox(): bool
    {
        return $this->demo_expires_at !== null;
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->gte(today());
    }

    /**
     * Whether the club may have members at all right now: a plan in force or a running trial.
     */
    public function hasMemberAccess(): bool
    {
        return $this->currentSubscription() !== null || $this->isOnTrial();
    }

    /**
     * Maximum number of active members right now; null means unlimited.
     */
    public function memberLimit(): ?int
    {
        if ($subscription = $this->currentSubscription()) {
            return $subscription->plan->memberLimit();
        }

        return $this->isOnTrial() ? ClubPlan::TRIAL_MEMBER_LIMIT : 0;
    }

    public function activeMemberCount(): int
    {
        return $this->memberships()->where('status', MembershipStatus::Active)->count();
    }

    public function canAddMembers(): bool
    {
        if (! $this->hasMemberAccess()) {
            return false;
        }

        $limit = $this->memberLimit();

        return $limit === null || $this->activeMemberCount() < $limit;
    }

    /**
     * @throws ClubPlanException
     */
    public function ensureCanAddMembers(): void
    {
        throw_unless($this->hasMemberAccess(), ClubPlanException::planRequired());

        $limit = $this->memberLimit();

        throw_if($limit !== null && $this->activeMemberCount() >= $limit, ClubPlanException::memberLimitReached($limit ?? 0));
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
