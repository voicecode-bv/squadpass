<?php

use App\Enums\MembershipStatus;
use App\Enums\StaffRole;
use App\Livewire\Members\Index;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->staff = User::factory()->create();
    $this->club->addStaff($this->staff, StaffRole::Admin);
});

test('staff can add an existing user as a member by email', function () {
    $member = User::factory()->create(['email' => 'lid@example.com']);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('email', 'lid@example.com')
        ->call('addMember')
        ->assertHasNoErrors();

    expect($member->isMemberOf($this->club))->toBeTrue();
});

test('adding an unknown email sends an invitation instead of a membership', function () {
    Mail::fake();

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('email', 'onbekend@example.com')
        ->call('addMember')
        ->assertHasNoErrors();

    expect(Membership::query()->count())->toBe(0)
        ->and(ClubInvitation::query()->where('email', 'onbekend@example.com')->exists())->toBeTrue();
});

test('membership numbers are sequential per club', function () {
    $otherClub = Club::factory()->create();

    $first = $this->club->addMember(User::factory()->create());
    $second = $this->club->addMember(User::factory()->create());
    $otherFirst = $otherClub->addMember(User::factory()->create());

    expect($first->membership_number)->toBe(1)
        ->and($second->membership_number)->toBe(2)
        ->and($otherFirst->membership_number)->toBe(1);
});

test('re-adding an ended member reactivates the membership and keeps the number', function () {
    $user = User::factory()->create();
    $membership = $this->club->addMember($user);
    $membership->update(['status' => MembershipStatus::Ended, 'ended_at' => now()]);

    $reactivated = $this->club->addMember($user);

    expect($reactivated->id)->toBe($membership->id)
        ->and($reactivated->status)->toBe(MembershipStatus::Active)
        ->and($reactivated->ended_at)->toBeNull()
        ->and($reactivated->membership_number)->toBe($membership->membership_number)
        ->and($this->club->memberships()->count())->toBe(1);
});

test('a user can be a member of multiple clubs', function () {
    $user = User::factory()->create();
    $otherClub = Club::factory()->create();

    $this->club->addMember($user);
    $otherClub->addMember($user);

    expect($user->isMemberOf($this->club))->toBeTrue()
        ->and($user->isMemberOf($otherClub))->toBeTrue()
        ->and($user->memberships()->count())->toBe(2);
});

test('staff can end and reactivate a membership', function () {
    $membership = $this->club->addMember(User::factory()->create());

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('updateStatus', $membership->id, 'ended');

    expect($membership->refresh()->status)->toBe(MembershipStatus::Ended)
        ->and($membership->ended_at)->not->toBeNull();

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('updateStatus', $membership->id, 'active');

    expect($membership->refresh()->status)->toBe(MembershipStatus::Active)
        ->and($membership->ended_at)->toBeNull();
});

test('staff of another club cannot manage members here', function () {
    $otherStaff = User::factory()->create();
    Club::factory()->create()->addStaff($otherStaff, StaffRole::Owner);

    Livewire::actingAs($otherStaff)
        ->test(Index::class, ['club' => $this->club])
        ->assertForbidden();
});

test('staff cannot update a membership of another club via the component', function () {
    $otherClub = Club::factory()->create();
    $foreignMembership = $otherClub->addMember(User::factory()->create());

    expect(fn () => Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('updateStatus', $foreignMembership->id, 'ended')
    )->toThrow(ModelNotFoundException::class);

    expect($foreignMembership->refresh()->status)->toBe(MembershipStatus::Active);
});

test('the member list only shows active memberships by default', function () {
    $active = User::factory()->create(['name' => 'Actief Lid']);
    $this->club->addMember($active);

    $ended = User::factory()->create(['name' => 'Gestopt Lid']);
    $this->club->addMember($ended)->update([
        'status' => MembershipStatus::Ended,
        'ended_at' => now()->toDateString(),
    ]);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->assertSee('Actief Lid')
        ->assertDontSee('Gestopt Lid');
});

test('staff can filter the member list on another status or show them all', function () {
    $active = User::factory()->create(['name' => 'Actief Lid']);
    $this->club->addMember($active);

    $paused = User::factory()->create(['name' => 'Gepauzeerd Lid']);
    $this->club->addMember($paused)->update(['status' => MembershipStatus::Paused]);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('status', MembershipStatus::Paused->value)
        ->assertSee('Gepauzeerd Lid')
        ->assertDontSee('Actief Lid')
        ->set('status', 'all')
        ->assertSee('Gepauzeerd Lid')
        ->assertSee('Actief Lid');
});
