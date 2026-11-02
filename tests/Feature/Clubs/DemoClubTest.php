<?php

use App\Enums\AddonKey;
use App\Enums\StaffRole;
use App\Livewire\Clubs\Moneybird;
use App\Livewire\Clubs\Settings;
use App\Models\Club;
use App\Models\User;
use App\Services\Moneybird\MoneybirdGateway;
use Livewire\Livewire;
use Tests\Fakes\FakeMoneybirdGateway;

beforeEach(function () {
    $this->app->instance(MoneybirdGateway::class, new FakeMoneybirdGateway);
});

function demoClubOwner(Club $club): User
{
    $owner = User::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);

    return $owner;
}

test('a demo club has every add-on unlocked without a subscription', function (AddonKey $key) {
    expect(Club::factory()->demo()->create()->hasAddon($key))->toBeTrue()
        ->and(Club::factory()->create()->hasAddon($key))->toBeFalse();
})->with(AddonKey::cases());

test('only superadmins may manage the integrations of a demo club', function () {
    $club = Club::factory()->create();
    $demoClub = Club::factory()->demo()->create();
    $owner = demoClubOwner($club);
    $demoOwner = demoClubOwner($demoClub);
    $admin = User::factory()->create();
    $club->addStaff($admin, StaffRole::Admin);
    $superadmin = User::factory()->superadmin()->create();

    expect($owner->can('manageIntegrations', $club))->toBeTrue()
        ->and($admin->can('manageIntegrations', $club))->toBeFalse()
        ->and($demoOwner->can('manageIntegrations', $demoClub))->toBeFalse()
        ->and($superadmin->can('manageIntegrations', $demoClub))->toBeTrue();
});

test('a superadmin can mark a club as a demo club', function () {
    $club = Club::factory()->create();

    Livewire::actingAs(User::factory()->superadmin()->create())
        ->test(Settings::class, ['club' => $club])
        ->set('is_demo', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($club->refresh()->is_demo)->toBeTrue();
});

test('an owner cannot mark their club as a demo club', function () {
    $club = Club::factory()->create();

    Livewire::actingAs(demoClubOwner($club))
        ->test(Settings::class, ['club' => $club])
        ->assertDontSee(__('Demo club'))
        ->set('is_demo', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($club->refresh()->is_demo)->toBeFalse();
});

test('the owner of a demo club cannot see or change the Mollie key', function () {
    $club = Club::factory()->demo()->create(['mollie_api_key' => 'live_managed_by_squadpass']);

    Livewire::actingAs(demoClubOwner($club))
        ->test(Settings::class, ['club' => $club])
        ->assertSet('mollie_api_key', '')
        ->assertDontSee('live_managed_by_squadpass')
        ->assertSee(__('This is a demo club: SquadPass manages the Mollie and Moneybird settings.'))
        ->set('mollie_api_key', 'test_owner_override')
        ->call('save')
        ->assertHasNoErrors();

    expect($club->refresh()->mollie_api_key)->toBe('live_managed_by_squadpass');
});

test('a superadmin can change the Mollie key of a demo club', function () {
    $club = Club::factory()->demo()->create();

    Livewire::actingAs(User::factory()->superadmin()->create())
        ->test(Settings::class, ['club' => $club])
        ->set('mollie_api_key', 'test_demo_key')
        ->call('save')
        ->assertHasNoErrors();

    expect($club->refresh()->mollie_api_key)->toBe('test_demo_key');
});

test('the owner of a demo club cannot connect or change Moneybird', function (string $action) {
    $club = Club::factory()->demo()->create([
        'moneybird_access_token' => 'mb_managed_by_squadpass',
        'moneybird_administration_id' => '123456',
    ]);

    Livewire::actingAs(demoClubOwner($club))
        ->test(Moneybird::class, ['club' => $club])
        ->assertSet('access_token', '')
        ->set('access_token', 'a-valid-looking-token-value')
        ->call($action)
        ->assertForbidden();

    expect($club->refresh()->moneybird_access_token)->toBe('mb_managed_by_squadpass');
})->with(['connect', 'saveSettings', 'disconnect']);

test('a superadmin can connect Moneybird for a demo club', function () {
    $club = Club::factory()->demo()->create();

    Livewire::actingAs(User::factory()->superadmin()->create())
        ->test(Moneybird::class, ['club' => $club])
        ->set('access_token', 'a-valid-looking-token-value')
        ->call('connect')
        ->assertHasNoErrors();

    expect($club->refresh()->moneybird_access_token)->toBe('a-valid-looking-token-value');
});
