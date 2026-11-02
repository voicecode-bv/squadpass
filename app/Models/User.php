<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\MembershipStatus;
use App\Enums\StaffRole;
use App\Services\PersonName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name full name, composed from the parts below by the model
 * @property string $first_name
 * @property string|null $name_prefix "tussenvoegsel" such as "van der"
 * @property string $last_name
 * @property Carbon|null $name_confirmed_at when the person last saved their own name; empty after an automatic split
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_superadmin
 * @property string|null $avatar_path
 * @property Carbon|null $product_tour_completed_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $demo_expires_at set on the fake accounts of a demo sandbox, which is deleted once it passes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'first_name', 'name_prefix', 'last_name', 'name_confirmed_at', 'email', 'password', 'avatar_path'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Keep the full name and its parts in sync. Changing the parts recomposes
     * the name; setting only the name (older app versions, seeders) splits it.
     * When both change and disagree, the explicitly set name wins.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            // A demo account keeps its undeliverable address, so nobody can use the
            // public demo to have mail sent to a real inbox.
            if ($user->exists && $user->isDemoSandbox() && $user->isDirty('email')) {
                $user->email = $user->getOriginal('email');
            }

            $partsChanged = $user->isDirty(['first_name', 'name_prefix', 'last_name']);
            $composed = PersonName::compose($user->first_name, $user->name_prefix, $user->last_name);

            if ($partsChanged && (! $user->isDirty('name') || $user->name === $composed)) {
                $user->name_prefix = filled($user->name_prefix) ? trim($user->name_prefix) : null;
                $user->name = $composed;

                return;
            }

            if ($user->isDirty('name')) {
                $user->forceFill(PersonName::split($user->name));
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'name_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'is_superadmin' => 'boolean',
            'product_tour_completed_at' => 'datetime',
            'demo_expires_at' => 'datetime',
        ];
    }

    /**
     * Whether the name was split automatically (existing account or imported
     * member) and the person has not checked it yet.
     */
    public function isDemoSandbox(): bool
    {
        return $this->demo_expires_at !== null;
    }

    public function needsNameReview(): bool
    {
        return $this->name_confirmed_at === null;
    }

    /**
     * Public URL of the uploaded avatar, or null when none is set.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null;
    }

    /**
     * Clubs where this user is a staff member (owner or admin).
     *
     * @return BelongsToMany<Club, $this>
     */
    public function staffClubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class, 'club_staff')
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
     * The trainer profiles of this user, one per club they train at.
     *
     * @return HasMany<Trainer, $this>
     */
    public function trainerProfiles(): HasMany
    {
        return $this->hasMany(Trainer::class);
    }

    /**
     * Clubs where this user has a membership record (any status).
     *
     * @return BelongsToMany<Club, $this>
     */
    public function memberClubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class, 'memberships')
            ->withPivot(['membership_number', 'status'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * @return HasMany<FormAssignment, $this>
     */
    public function formAssignments(): HasMany
    {
        return $this->hasMany(FormAssignment::class);
    }

    public function isStaffOf(Club $club, ?StaffRole $role = null): bool
    {
        $query = $this->staffClubs()->whereKey($club->id);

        if ($role !== null) {
            $query->wherePivot('role', $role->value);
        }

        return $query->exists();
    }

    public function ownsAnyClub(): bool
    {
        return $this->staffClubs()
            ->wherePivot('role', StaffRole::Owner->value)
            ->exists();
    }

    public function isMemberOf(Club $club): bool
    {
        return $this->memberships()
            ->where('club_id', $club->id)
            ->where('status', MembershipStatus::Active)
            ->exists();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
