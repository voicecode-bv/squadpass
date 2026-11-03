<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Database\Factories\MembershipFactory;
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
 * @property int $user_id
 * @property int $membership_number
 * @property MembershipStatus $status
 * @property Carbon $joined_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $trial_started_at set when the membership started as a trial lesson; kept after converting
 * @property string|null $phone kept by the club, e.g. from an imported member list
 * @property Carbon|null $date_of_birth kept by the club, e.g. from an imported member list
 * @property string|null $moneybird_contact_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['club_id', 'user_id', 'membership_number', 'status', 'joined_at', 'ended_at', 'trial_started_at', 'phone', 'date_of_birth', 'moneybird_contact_id'])]
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'membership_number' => 'integer',
            'status' => MembershipStatus::class,
            'joined_at' => 'date',
            'ended_at' => 'date',
            'trial_started_at' => 'date',
            'date_of_birth' => 'date',
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
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', MembershipStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }
}
