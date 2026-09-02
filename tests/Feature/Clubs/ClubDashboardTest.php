<?php

use App\Enums\StaffRole;
use App\Livewire\Clubs\Dashboard;
use App\Models\Club;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

test('staff can view the club dashboard', function () {
    $this->actingAs($this->owner)
        ->get(route('clubs.dashboard', $this->club))
        ->assertOk()
        ->assertSee($this->club->name);
});

test('non-staff users cannot view the club dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('clubs.dashboard', $this->club))
        ->assertForbidden();
});

test('statistics are scoped to the club', function () {
    Membership::factory()->count(2)->for($this->club)->create(['joined_at' => now()->startOfMonth()->addDay()->toDateString()]);
    Membership::factory()->create(['joined_at' => now()->startOfMonth()->addDay()->toDateString()]);
    Payment::factory()->for($this->club)->paid()->create(['amount_cents' => 1500, 'paid_at' => now()->startOfMonth()->subMonth()->addDay()]);
    Payment::factory()->paid()->create(['amount_cents' => 9000, 'paid_at' => now()->startOfMonth()->subMonth()->addDay()]);

    $instance = Livewire::actingAs($this->owner)
        ->test(Dashboard::class, ['club' => $this->club])
        ->instance();

    expect($instance->activeMemberCount)->toBe(2)
        ->and($instance->newMembersPerMonth['values'][11])->toBe(2)
        ->and($instance->newMembersLastMonth)->toBe(0)
        ->and($instance->revenueLastMonthCents)->toBe(1500);
});
