<?php

use App\Enums\AddonKey;
use App\Enums\StaffRole;
use App\Livewire\Clubs\Settings;
use App\Livewire\Portal\Subscriptions as PortalSubscriptions;
use App\Models\Club;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Mollie\MollieGateway;
use Livewire\Livewire;
use Tests\Fakes\FakeMollieGateway;

beforeEach(function () {
    $this->app->instance(MollieGateway::class, new FakeMollieGateway);
});

test('members cannot pay online at a club with a Mollie key but without the Mollie add-on', function () {
    $club = Club::factory()->create(['mollie_api_key' => 'test_key']);
    $member = User::factory()->create();
    $club->addMember($member);
    $plan = SubscriptionPlan::factory()->for($club)->create();

    Livewire::actingAs($member)
        ->test(PortalSubscriptions::class)
        ->call('subscribe', $plan->id)
        ->assertNoRedirect();

    expect(Payment::query()->count())->toBe(0)
        ->and($club->acceptsOnlinePayments())->toBeFalse();
});

test('a club with the Mollie add-on and a key accepts online payments', function () {
    expect(Club::factory()->withMollieKey()->create()->acceptsOnlinePayments())->toBeTrue();
});

test('moneybird counts as configured only when the Moneybird add-on is active', function () {
    $withoutAddon = Club::factory()->create(['moneybird_access_token' => 'mb', 'moneybird_administration_id' => '1']);
    $withAddon = Club::factory()->withMoneybird()->create();

    expect($withoutAddon->hasMoneybirdConfigured())->toBeFalse()
        ->and($withAddon->hasMoneybirdConfigured())->toBeTrue()
        ->and($withAddon->hasAddon(AddonKey::Mollie))->toBeFalse();
});

test('club settings hide and ignore the Mollie key without the Mollie add-on', function () {
    $club = Club::factory()->create();
    $owner = User::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);

    Livewire::actingAs($owner)
        ->test(Settings::class, ['club' => $club])
        ->assertSee(__('Activate the Mollie add-on to let members pay online for strip cards and subscriptions.'))
        ->assertDontSee(__('Mollie API key'))
        ->set('mollie_api_key', 'test_sneaky')
        ->call('save')
        ->assertHasNoErrors();

    expect($club->refresh()->mollie_api_key)->toBeNull();
});

test('club settings show the Mollie key field once the add-on is active', function () {
    $club = Club::factory()->withMollieKey()->create();
    $owner = User::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);

    Livewire::actingAs($owner)
        ->test(Settings::class, ['club' => $club])
        ->assertSee(__('Mollie API key'))
        ->set('mollie_api_key', 'test_new_key')
        ->call('save')
        ->assertHasNoErrors();

    expect($club->refresh()->mollie_api_key)->toBe('test_new_key');
});
