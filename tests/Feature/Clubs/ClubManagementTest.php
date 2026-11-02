<?php

use App\Enums\StaffRole;
use App\Livewire\Clubs\Index;
use App\Livewire\Clubs\Settings;
use App\Models\Club;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('an existing club owner can create a club and becomes its owner', function () {
    $user = User::factory()->create();
    Club::factory()->create()->addStaff($user, StaffRole::Owner);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('name', 'Squash Club Utrecht')
        ->call('createClub')
        ->assertHasNoErrors()
        ->assertRedirect();

    $club = Club::query()->where('name', 'Squash Club Utrecht')->firstOrFail();

    expect($club->slug)->toBe('squash-club-utrecht')
        ->and($user->isStaffOf($club, StaffRole::Owner))->toBeTrue();
});

test('club slugs are unique even for identical names', function () {
    $user = User::factory()->superadmin()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('name', 'Padel Club')
        ->call('createClub');

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('name', 'Padel Club')
        ->call('createClub');

    $slugs = Club::query()->pluck('slug');

    expect($slugs)->toHaveCount(2)
        ->and($slugs->unique())->toHaveCount(2);
});

test('an owner can update club settings', function () {
    $owner = User::factory()->create();
    $club = Club::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);

    Livewire::actingAs($owner)
        ->test(Settings::class, ['club' => $club])
        ->set('name', 'Renamed Club')
        ->set('self_signup', false)
        ->call('save')
        ->assertHasNoErrors();

    $club->refresh();

    expect($club->name)->toBe('Renamed Club')
        ->and($club->self_signup)->toBeFalse();
});

test('an admin cannot access club settings', function () {
    $admin = User::factory()->create();
    $club = Club::factory()->create();
    $club->addStaff($admin, StaffRole::Admin);

    Livewire::actingAs($admin)
        ->test(Settings::class, ['club' => $club])
        ->assertForbidden();
});

test('a non-staff user cannot access club settings', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();

    Livewire::actingAs($user)
        ->test(Settings::class, ['club' => $club])
        ->assertForbidden();
});

test('a validation error on another tab switches to that tab', function () {
    $owner = User::factory()->create();
    $club = Club::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);

    Livewire::actingAs($owner)
        ->withQueryParams(['tab' => 'registration'])
        ->test(Settings::class, ['club' => $club])
        ->assertSet('tab', 'registration')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name'])
        ->assertSet('tab', 'general');
});

test('the mollie api key is stored encrypted', function () {
    $club = Club::factory()->withMollieKey('test_secret')->create();

    $raw = DB::table('clubs')->where('id', $club->id)->value('mollie_api_key');

    expect($raw)->not->toContain('test_secret')
        ->and($club->refresh()->mollie_api_key)->toBe('test_secret');
});
