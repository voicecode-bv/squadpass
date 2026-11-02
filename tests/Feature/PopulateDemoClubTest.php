<?php

use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;

test('a demo club is filled with fake members and their history, reusing its plans on a second run', function () {
    $club = Club::factory()->demo()->create(['slug' => 'demo-gym']);
    $club->addStaff(User::factory()->create(), StaffRole::Owner);

    $this->artisan('demo:populate', ['club' => 'demo-gym', '--members' => 30])->assertSuccessful();
    $this->artisan('demo:populate', ['club' => 'demo-gym', '--members' => 10])->assertSuccessful();

    expect($club->memberships()->count())->toBe(40)
        ->and($club->memberships()->where('status', MembershipStatus::Active)->count())->toBeGreaterThan(30)
        ->and(Payment::query()->where('club_id', $club->id)->where('status', PaymentStatus::Paid)->count())->toBeGreaterThan(20)
        ->and(CheckIn::query()->where('club_id', $club->id)->count())->toBeGreaterThan(0)
        ->and($club->lessons()->count())->toBeGreaterThan(0)
        ->and(SubscriptionPlan::query()->where('club_id', $club->id)->count())->toBe(4);
});

test('a club that is not a demo club is never populated', function () {
    $club = Club::factory()->create(['slug' => 'real-gym']);
    $club->addStaff(User::factory()->create(), StaffRole::Owner);

    $this->artisan('demo:populate', ['club' => 'real-gym'])->assertFailed();

    expect($club->memberships()->count())->toBe(0);
});
