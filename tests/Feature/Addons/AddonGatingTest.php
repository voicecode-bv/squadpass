<?php

use App\Enums\AddonKey;
use App\Enums\StaffRole;
use App\Livewire\Clubs\AddonCta;
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

test('club settings pitch the Mollie add-on until the club has it', function () {
    $club = Club::factory()->create();
    $owner = User::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);

    Livewire::actingAs($owner)
        ->test(Settings::class, ['club' => $club])
        ->assertSeeLivewire(AddonCta::class)
        ->assertDontSee(route('clubs.mollie', $club));
});

test('club settings link to the Mollie page once the add-on is active', function () {
    $club = Club::factory()->withMollieKey()->create();
    $owner = User::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);

    Livewire::actingAs($owner)
        ->test(Settings::class, ['club' => $club])
        ->assertSee(route('clubs.mollie', $club))
        ->assertSee(__('Connected in test mode.'));
});
