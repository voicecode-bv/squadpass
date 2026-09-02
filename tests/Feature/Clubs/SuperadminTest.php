<?php

use App\Enums\MembershipStatus;
use App\Enums\StaffRole;
use App\Livewire\Clubs\Index;
use App\Livewire\Clubs\Settings;
use App\Livewire\Members\Index as MembersIndex;
use App\Models\Club;
use App\Models\User;
use Livewire\Livewire;

test('a member cannot create a club', function () {
    $member = User::factory()->create();
    Club::factory()->create()->addMember($member);

    Livewire::actingAs($member)
        ->test(Index::class)
        ->set('name', 'Eigen Club')
        ->call('createClub')
        ->assertForbidden();

    expect(Club::query()->count())->toBe(1);
});

test('a fresh user without clubs cannot create a club', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->set('name', 'Eigen Club')
        ->call('createClub')
        ->assertForbidden();
});

test('a superadmin can create a club and assign an owner by email', function () {
    $superadmin = User::factory()->superadmin()->create();
    $owner = User::factory()->create(['email' => 'eigenaar@example.com']);

    Livewire::actingAs($superadmin)
        ->test(Index::class)
        ->set('name', 'Nieuwe Club')
        ->set('owner_email', 'eigenaar@example.com')
        ->call('createClub')
        ->assertHasNoErrors();

    $club = Club::query()->where('name', 'Nieuwe Club')->firstOrFail();

    expect($owner->isStaffOf($club, StaffRole::Owner))->toBeTrue()
        ->and($superadmin->isStaffOf($club))->toBeFalse();
});

test('assigning an unknown owner email shows a validation error', function () {
    $superadmin = User::factory()->superadmin()->create();

    Livewire::actingAs($superadmin)
        ->test(Index::class)
        ->set('name', 'Nieuwe Club')
        ->set('owner_email', 'onbekend@example.com')
        ->call('createClub')
        ->assertHasErrors(['owner_email']);

    expect(Club::query()->count())->toBe(0);
});

test('a superadmin sees all clubs', function () {
    $superadmin = User::factory()->superadmin()->create();
    Club::factory()->create(['name' => 'Club Alfa']);
    Club::factory()->create(['name' => 'Club Beta']);

    Livewire::actingAs($superadmin)
        ->test(Index::class)
        ->assertSee('Club Alfa')
        ->assertSee('Club Beta');
});

test('a superadmin can manage members of any club', function () {
    $superadmin = User::factory()->superadmin()->create();
    $club = Club::factory()->create();
    $membership = $club->addMember(User::factory()->create());

    Livewire::actingAs($superadmin)
        ->test(MembersIndex::class, ['club' => $club])
        ->call('updateStatus', $membership->id, 'ended');

    expect($membership->refresh()->status)->toBe(MembershipStatus::Ended);
});

test('a superadmin can change any clubs settings', function () {
    $superadmin = User::factory()->superadmin()->create();
    $club = Club::factory()->create();

    Livewire::actingAs($superadmin)
        ->test(Settings::class, ['club' => $club])
        ->set('name', 'Hernoemd door superadmin')
        ->call('save')
        ->assertHasNoErrors();

    expect($club->refresh()->name)->toBe('Hernoemd door superadmin');
});

test('a regular owner still cannot manage another club', function () {
    $owner = User::factory()->create();
    Club::factory()->create()->addStaff($owner, StaffRole::Owner);
    $otherClub = Club::factory()->create();

    Livewire::actingAs($owner)
        ->test(MembersIndex::class, ['club' => $otherClub])
        ->assertForbidden();
});
