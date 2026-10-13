<?php

use App\Enums\ClubPlan;
use App\Enums\MembershipStatus;
use App\Enums\StaffRole;
use App\Livewire\Clubs\Join;
use App\Livewire\Invitations\Accept;
use App\Livewire\Members\Index as MembersIndex;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\ClubSubscription;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

/**
 * Bulk-insert active members, so limit tests stay fast without hundreds of factory calls.
 */
function fillWithActiveMembers(Club $club, int $count): void
{
    $prefix = Str::lower(Str::random(8));
    $now = now();

    foreach (array_chunk(range(1, $count), 250) as $chunk) {
        DB::table('users')->insert(array_map(fn (int $index) => [
            'name' => "Member {$index}",
            'email' => "{$prefix}-{$index}@example.com",
            'password' => 'not-used',
            'created_at' => $now,
            'updated_at' => $now,
        ], $chunk));
    }

    $firstNumber = $club->fresh()->next_membership_number;

    $memberships = DB::table('users')->where('email', 'like', "{$prefix}-%")->pluck('id')
        ->values()
        ->map(fn (int $userId, int $index) => [
            'club_id' => $club->id,
            'user_id' => $userId,
            'membership_number' => $firstNumber + $index,
            'status' => MembershipStatus::Active->value,
            'joined_at' => $now->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

    foreach ($memberships->chunk(250) as $chunk) {
        DB::table('memberships')->insert($chunk->values()->all());
    }

    $club->forceFill(['next_membership_number' => $firstNumber + $count])->save();
}

function expireTrial(Club $club): void
{
    $club->forceFill(['trial_ends_at' => today()->subDay()])->save();
}

test('a new club starts with a fourteen day trial in which members can be added', function () {
    expect($this->club->trial_ends_at->toDateString())->toBe(today()->addDays(ClubPlan::TRIAL_DAYS)->toDateString())
        ->and($this->club->canAddMembers())->toBeTrue();

    $this->club->addMember(User::factory()->create());

    expect($this->club->activeMemberCount())->toBe(1);
});

test('staff cannot add members once the trial has ended without a plan', function () {
    expireTrial($this->club);
    $user = User::factory()->create();

    Livewire::actingAs($this->owner)
        ->test(MembersIndex::class, ['club' => $this->club])
        ->assertSee(__('No active SquadPass plan'))
        ->set('email', $user->email)
        ->call('addMember')
        ->assertHasErrors('email');

    expect($user->isMemberOf($this->club))->toBeFalse();
});

test('an active plan lets staff add members after the trial', function () {
    expireTrial($this->club);
    ClubSubscription::factory()->active()->create(['club_id' => $this->club->id]);
    $user = User::factory()->create();

    Livewire::actingAs($this->owner)
        ->test(MembersIndex::class, ['club' => $this->club])
        ->set('email', $user->email)
        ->call('addMember')
        ->assertHasNoErrors();

    expect($user->isMemberOf($this->club))->toBeTrue();
});

test('a cancelled plan keeps working until its paid period ends', function () {
    expireTrial($this->club);
    ClubSubscription::factory()->cancelled()->create(['club_id' => $this->club->id]);

    expect($this->club->canAddMembers())->toBeTrue();

    $this->travel(11)->days();

    expect($this->club->fresh()->canAddMembers())->toBeFalse();
});

test('an overdue plan blocks adding members', function () {
    expireTrial($this->club);
    ClubSubscription::factory()->pastDue()->create(['club_id' => $this->club->id]);

    expect($this->club->canAddMembers())->toBeFalse();
});

test('member invitations cannot be accepted or sent without a plan', function () {
    expireTrial($this->club);
    $invitation = ClubInvitation::factory()->member()->create(['club_id' => $this->club->id]);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Accept::class, ['token' => $invitation->token])
        ->call('accept');

    expect($user->isMemberOf($this->club))->toBeFalse()
        ->and($invitation->refresh()->accepted_at)->toBeNull();

    Livewire::actingAs($this->owner)
        ->test(MembersIndex::class, ['club' => $this->club])
        ->set('email', 'new-person@example.com')
        ->call('addMember')
        ->assertHasErrors('email');

    expect(ClubInvitation::query()->where('email', 'new-person@example.com')->exists())->toBeFalse();
});

test('visitors cannot sign themselves up without a plan', function () {
    expireTrial($this->club);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Join::class, ['club' => $this->club])
        ->call('join')
        ->assertNoRedirect();

    expect($user->isMemberOf($this->club))->toBeFalse();
});

test('reactivating an ended membership counts as adding a member', function () {
    $membership = Membership::factory()->create([
        'club_id' => $this->club->id,
        'status' => MembershipStatus::Ended,
        'ended_at' => today()->subMonth(),
    ]);
    expireTrial($this->club);

    Livewire::actingAs($this->owner)
        ->test(MembersIndex::class, ['club' => $this->club])
        ->call('updateStatus', $membership->id, MembershipStatus::Active->value);

    expect($membership->refresh()->status)->toBe(MembershipStatus::Ended);
});

test('the member limit of the plan is enforced', function () {
    expireTrial($this->club);
    ClubSubscription::factory()->active()->plan(ClubPlan::Starter)->create(['club_id' => $this->club->id]);
    fillWithActiveMembers($this->club, 500);

    $user = User::factory()->create();

    Livewire::actingAs($this->owner)
        ->test(MembersIndex::class, ['club' => $this->club])
        ->assertSee(__('Member limit reached'))
        ->set('email', $user->email)
        ->call('addMember')
        ->assertHasErrors('email');

    expect($user->isMemberOf($this->club))->toBeFalse();

    // A larger plan raises the limit right away.
    ClubSubscription::query()->update(['plan' => ClubPlan::Growth]);

    expect($this->club->fresh()->canAddMembers())->toBeTrue();
});

test('the trial is limited to the smallest plan size', function () {
    fillWithActiveMembers($this->club, ClubPlan::TRIAL_MEMBER_LIMIT);

    expect($this->club->canAddMembers())->toBeFalse();
});

test('an enterprise plan has no member limit', function () {
    expireTrial($this->club);
    ClubSubscription::factory()->active()->plan(ClubPlan::Enterprise)->create([
        'club_id' => $this->club->id,
        'mollie_mandate_id' => null,
        'next_billing_at' => null,
    ]);

    expect($this->club->memberLimit())->toBeNull()
        ->and($this->club->canAddMembers())->toBeTrue();
});
