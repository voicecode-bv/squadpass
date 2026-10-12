<?php

use App\Enums\BillingInterval;
use App\Enums\StaffRole;
use App\Livewire\SubscriptionPlans\Index;
use App\Models\Club;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->staff = User::factory()->create();
    $this->club->addStaff($this->staff, StaffRole::Admin);
});

test('staff can create a subscription plan', function () {
    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('name', 'Onbeperkt')
        ->set('price', '39.50')
        ->set('interval', BillingInterval::Monthly->value)
        ->set('credits', 8)
        ->call('savePlan')
        ->assertHasNoErrors();

    $plan = SubscriptionPlan::query()->firstOrFail();

    expect($plan->name)->toBe('Onbeperkt')
        ->and($plan->price_cents)->toBe(3950)
        ->and($plan->credits)->toBe(8)
        ->and($plan->club_id)->toBe($this->club->id);
});

test('staff can edit a subscription plan', function () {
    $plan = SubscriptionPlan::factory()->create([
        'club_id' => $this->club->id,
        'name' => 'Onbeperkt',
        'price_cents' => 3950,
        'credits' => 8,
    ]);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('editPlan', $plan->id)
        ->assertSet('name', 'Onbeperkt')
        ->assertSet('price', '39.50')
        ->set('name', 'Onbeperkt plus')
        ->set('price', '45.00')
        ->set('credits', null)
        ->call('savePlan')
        ->assertHasNoErrors();

    $plan->refresh();

    expect($plan->name)->toBe('Onbeperkt plus')
        ->and($plan->price_cents)->toBe(4500)
        ->and($plan->isUnlimited())->toBeTrue()
        ->and(SubscriptionPlan::query()->count())->toBe(1);
});

test('a subscription plan of another club cannot be edited', function () {
    $otherPlan = SubscriptionPlan::factory()->create();

    expect(fn () => Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('editPlan', $otherPlan->id)
    )->toThrow(ModelNotFoundException::class);
});

test('non-staff cannot manage subscription plans', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class, ['club' => $this->club])
        ->assertForbidden();
});
