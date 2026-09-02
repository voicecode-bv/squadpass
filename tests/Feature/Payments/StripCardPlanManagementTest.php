<?php

use App\Enums\StaffRole;
use App\Livewire\StripCardPlans\Index;
use App\Models\Club;
use App\Models\StripCardPlan;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->staff = User::factory()->create();
    $this->club->addStaff($this->staff, StaffRole::Admin);
});

test('staff can create a strip card plan', function () {
    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('name', '10-strippenkaart')
        ->set('strips', 10)
        ->set('price', '75.00')
        ->set('validity_months', 12)
        ->call('createPlan')
        ->assertHasNoErrors();

    $plan = StripCardPlan::query()->firstOrFail();

    expect($plan->price_cents)->toBe(7500)
        ->and($plan->strips)->toBe(10)
        ->and($plan->club_id)->toBe($this->club->id);
});

test('staff can archive and reactivate a plan', function () {
    $plan = StripCardPlan::factory()->for($this->club)->create();

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('toggleActive', $plan->id);

    expect($plan->refresh()->active)->toBeFalse();
});

test('non-staff cannot manage strip card plans', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class, ['club' => $this->club])
        ->assertForbidden();
});
