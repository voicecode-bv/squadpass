<?php

use App\Enums\StaffRole;
use App\Livewire\StripCardPlans\Index;
use App\Models\Club;
use App\Models\StripCardPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        ->call('savePlan')
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

test('staff can edit a strip card plan', function () {
    $plan = StripCardPlan::factory()->create([
        'club_id' => $this->club->id,
        'name' => '10-strippenkaart',
        'strips' => 10,
        'price_cents' => 7500,
        'validity_months' => 12,
    ]);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('editPlan', $plan->id)
        ->assertSet('name', '10-strippenkaart')
        ->assertSet('price', '75.00')
        ->set('name', '10-rittenkaart')
        ->set('price', '80.00')
        ->set('validity_months', 6)
        ->call('savePlan')
        ->assertHasNoErrors();

    $plan->refresh();

    expect($plan->name)->toBe('10-rittenkaart')
        ->and($plan->price_cents)->toBe(8000)
        ->and($plan->validity_months)->toBe(6)
        ->and(StripCardPlan::query()->count())->toBe(1);
});

test('a strip card plan of another club cannot be edited', function () {
    $otherPlan = StripCardPlan::factory()->create();

    expect(fn () => Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('editPlan', $otherPlan->id)
    )->toThrow(ModelNotFoundException::class);
});
