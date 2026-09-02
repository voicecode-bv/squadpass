<?php

use App\Enums\MembershipStatus;
use App\Enums\StaffRole;
use App\Livewire\Clubs\Join;
use App\Livewire\Clubs\Settings;
use App\Models\Club;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create(['self_signup' => true]);
});

test('a guest sees login and register options on the join page', function () {
    $this->get(route('clubs.join', $this->club))
        ->assertOk()
        ->assertSee($this->club->name)
        ->assertSee(__('Log in'))
        ->assertSee(__('Create an account'));
});

test('a logged in user can join a club directly', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Join::class, ['club' => $this->club])
        ->call('join')
        ->assertRedirect(route('portal.schedule'));

    expect($user->isMemberOf($this->club))->toBeTrue()
        ->and($this->club->memberships()->firstOrFail()->membership_number)->toBe(1);
});

test('joining twice does not create a second membership', function () {
    $user = User::factory()->create();
    $this->club->addMember($user);

    Livewire::actingAs($user)
        ->test(Join::class, ['club' => $this->club])
        ->call('join');

    expect($this->club->memberships()->count())->toBe(1);
});

test('a returning ended member is reactivated with the old number', function () {
    $user = User::factory()->create();
    $membership = $this->club->addMember($user);
    $membership->update(['status' => MembershipStatus::Ended, 'ended_at' => now()]);

    Livewire::actingAs($user)
        ->test(Join::class, ['club' => $this->club])
        ->call('join');

    expect($membership->refresh()->status)->toBe(MembershipStatus::Active)
        ->and($this->club->memberships()->count())->toBe(1);
});

test('joining is blocked when self signup is disabled', function () {
    $this->club->update(['self_signup' => false]);
    $user = User::factory()->create();

    $this->get(route('clubs.join', $this->club))
        ->assertOk()
        ->assertSee(__('Registration closed'));

    Livewire::actingAs($user)
        ->test(Join::class, ['club' => $this->club])
        ->call('join')
        ->assertForbidden();

    expect($user->isMemberOf($this->club))->toBeFalse();
});

test('after logging in via the join page the user returns to it', function () {
    $user = User::factory()->create(['email' => 'lid@example.com', 'password' => 'password']);

    Livewire::test(Join::class, ['club' => $this->club])
        ->call('goToLogin')
        ->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => 'lid@example.com',
        'password' => 'password',
    ])->assertRedirect(route('clubs.join', $this->club));
});

test('the owner can toggle self signup in club settings', function () {
    $owner = User::factory()->create();
    $this->club->addStaff($owner, StaffRole::Owner);

    Livewire::actingAs($owner)
        ->test(Settings::class, ['club' => $this->club])
        ->set('self_signup', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->club->refresh()->self_signup)->toBeFalse();
});
