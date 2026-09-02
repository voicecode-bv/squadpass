<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\StaffRole;
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
 * @property string|null $moneybird_access_token
 * @property string|null $moneybird_administration_id
 * @property int $next_membership_number
 * @property int $next_ticket_number
 * @property bool $self_signup
 * @property Carbon|null $onboarded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'timezone', 'currency', 'mollie_api_key', 'mollie_profile_id', 'moneybird_access_token', 'moneybird_administration_id', 'self_signup', 'onboarded_at'])]
#[Hidden(['mollie_api_key', 'moneybird_access_token'])]
class Club extends Model
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

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

    public function hasMoneybirdConfigured(): bool
    {
        return filled($this->moneybird_access_token) && filled($this->moneybird_administration_id);
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

            return $membership;
        }

        return $this->memberships()->create([
            'user_id' => $user->id,
            'membership_number' => $this->allocateMembershipNumber(),
            'status' => MembershipStatus::Active,
            'joined_at' => now()->toDateString(),
        ]);
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
