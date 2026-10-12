<?php

use App\Enums\PaymentStatus;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\LessonEnrollment;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;

test('the demo seeder builds three fully populated clubs', function () {
    $this->seed();

    $clubs = Club::all();

    expect($clubs)->toHaveCount(3);

    $clubs->each(function (Club $club) {
        expect($club->memberships()->count())->toBeGreaterThanOrEqual(40)
            ->and($club->trainers()->count())->toBeGreaterThanOrEqual(3)
            ->and($club->lessons()->count())->toBe(6)
            ->and($club->occurrences()->count())->toBeGreaterThan(0)
            ->and($club->mollie_api_key)->toStartWith('test_')
            ->and($club->tickets()->count())->toBeGreaterThanOrEqual(4)
            ->and(CheckIn::query()->where('club_id', $club->id)->count())->toBeGreaterThan(50)
            ->and(LessonEnrollment::query()->where('club_id', $club->id)->count())->toBeGreaterThan(10)
            ->and(Payment::query()->where('club_id', $club->id)->where('status', PaymentStatus::Paid)->count())->toBeGreaterThan(20);
    });

    $owner = User::query()->where('email', 'owner@squadpass.nl')->firstOrFail();
    $member = User::query()->where('email', 'lid1@squadpass.nl')->firstOrFail();

    expect($owner->staffClubs()->count())->toBe(3)
        ->and($member->memberships()->count())->toBe(1);
});

test('every seeded user has a squadpass.nl e-mail address', function () {
    $this->seed();

    expect(User::query()->where('email', 'not like', '%@squadpass.nl')->count())->toBe(0)
        ->and(User::query()->count())->toBeGreaterThan(190);
});

test('every seeded club offers the same subscription ladder', function () {
    $this->seed();

    Club::all()->each(function (Club $club) {
        $plans = SubscriptionPlan::query()
            ->where('club_id', $club->id)
            ->orderBy('price_cents')
            ->get();

        expect($plans)->toHaveCount(4)
            ->and($plans->whereNull('credits'))->toHaveCount(1)
            ->and($plans->last()->isUnlimited())->toBeTrue()
            ->and($plans->pluck('credits')->filter()->values()->all())->toBe([4, 8, 12]);
    });
});
