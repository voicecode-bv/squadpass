<?php

use App\Enums\ClubPlan;
use App\Enums\StaffRole;
use App\Livewire\Admin\ClubPlans;
use App\Livewire\Clubs\Plan;
use App\Models\Club;
use App\Models\ClubPlanSetting;
use App\Models\ClubSubscription;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->superadmin = User::factory()->superadmin()->create();
});

test('a superadmin changes the price and member limit of a plan', function () {
    Livewire::actingAs($this->superadmin)
        ->test(ClubPlans::class)
        ->call('edit', ClubPlan::Starter->value)
        ->assertSet('price', '99.00')
        ->assertSet('member_limit', '500')
        ->set('price', '119.50')
        ->set('member_limit', '750')
        ->call('save')
        ->assertHasNoErrors();

    expect(ClubPlan::Starter->priceCents())->toBe(11950)
        ->and(ClubPlan::Starter->memberLimit())->toBe(750);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('Up to :count members', ['count' => '750']))
        ->assertSee('€ 119,50');
});

test('a new price only applies to new subscriptions, a new limit applies right away', function () {
    $club = Club::factory()->create();
    $subscription = ClubSubscription::factory()->active()->plan(ClubPlan::Starter)->create(['club_id' => $club->id]);

    Livewire::actingAs($this->superadmin)
        ->test(ClubPlans::class)
        ->call('edit', ClubPlan::Starter->value)
        ->set('price', '150')
        ->set('member_limit', '600')
        ->call('save');

    expect($subscription->fresh()->price_cents)->toBe(9900)
        ->and($club->fresh()->memberLimit())->toBe(600);
});

test('empty fields make a plan unlimited and priced on request', function () {
    Livewire::actingAs($this->superadmin)
        ->test(ClubPlans::class)
        ->call('edit', ClubPlan::Growth->value)
        ->set('price', '')
        ->set('member_limit', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(ClubPlan::Growth->priceCents())->toBeNull()
        ->and(ClubPlan::Growth->memberLimit())->toBeNull()
        ->and(ClubPlan::Growth->isSelfService())->toBeFalse();

    $owner = User::factory()->create();
    $club = Club::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);

    Livewire::actingAs($owner)
        ->test(Plan::class, ['club' => $club])
        ->call('subscribe', ClubPlan::Growth->value);

    expect(ClubSubscription::query()->exists())->toBeFalse();
});

test('the plan settings are validated', function () {
    Livewire::actingAs($this->superadmin)
        ->test(ClubPlans::class)
        ->call('edit', ClubPlan::Starter->value)
        ->set('price', '-1')
        ->set('member_limit', '0')
        ->call('save')
        ->assertHasErrors(['price', 'member_limit']);

    expect(ClubPlanSetting::query()->where('plan', ClubPlan::Starter)->value('price_cents'))->toBe(9900);
});

test('a club owner cannot manage the plans', function () {
    $owner = User::factory()->create();
    Club::factory()->create()->addStaff($owner, StaffRole::Owner);

    $this->actingAs($owner)->get(route('admin.club-plans'))->assertForbidden();

    Livewire::actingAs($owner)
        ->test(ClubPlans::class)
        ->assertForbidden();
});
