<?php

use App\Enums\PaymentStatus;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\LessonEnrollment;
use App\Models\Payment;
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

    $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
    $member = User::query()->where('email', 'lid1@example.com')->firstOrFail();

    expect($owner->staffClubs()->count())->toBe(3)
        ->and($member->memberships()->count())->toBe(1);
});
