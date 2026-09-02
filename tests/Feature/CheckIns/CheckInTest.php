<?php

use App\Actions\CheckIns\CheckInMember;
use App\Actions\Lessons\EnrollSingle;
use App\Enums\StaffRole;
use App\Exceptions\CheckInException;
use App\Livewire\CheckIns\Desk;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\LessonOccurrence;
use App\Models\StripCard;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);
});

test('a strip is redeemed for a free visit', function () {
    $card = StripCard::factory()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
        'strips_remaining' => 3,
    ]);

    $checkIn = app(CheckInMember::class)->handle($this->member, $this->club);

    expect($checkIn->strip_card_id)->toBe($card->id)
        ->and($card->refresh()->strips_remaining)->toBe(2)
        ->and($checkIn->coverageLabel())->toBe(__('Strip card'));
});

test('the oldest expiring usable card is redeemed first', function () {
    $later = StripCard::factory()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
        'expires_at' => now()->addYear(),
    ]);
    $sooner = StripCard::factory()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
        'expires_at' => now()->addMonth(),
    ]);
    StripCard::factory()->expired()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
    ]);

    $checkIn = app(CheckInMember::class)->handle($this->member, $this->club);

    expect($checkIn->strip_card_id)->toBe($sooner->id)
        ->and($later->refresh()->strips_remaining)->toBe(10);
});

test('an unlimited subscription covers the visit without using strips', function () {
    $plan = SubscriptionPlan::factory()->for($this->club)->create(['credits' => null]);
    $subscription = Subscription::factory()->active()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
        'subscription_plan_id' => $plan->id,
        'credits_remaining' => null,
    ]);
    $card = StripCard::factory()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
    ]);

    $checkIn = app(CheckInMember::class)->handle($this->member, $this->club);

    expect($checkIn->subscription_id)->toBe($subscription->id)
        ->and($checkIn->strip_card_id)->toBeNull()
        ->and($card->refresh()->strips_remaining)->toBe(10);
});

test('a lesson reservation covers the check-in without a strip', function () {
    $lesson = Lesson::factory()->for($this->club)->create();
    $occurrence = LessonOccurrence::factory()->for($lesson)->create([
        'club_id' => $this->club->id,
        'starts_at' => now()->addHours(2),
        'ends_at' => now()->addHours(3),
    ]);
    StripCard::factory()->create(['club_id' => $this->club->id, 'user_id' => $this->member->id]);

    app(EnrollSingle::class)->handle($this->member, $occurrence);

    $checkIn = app(CheckInMember::class)->handle($this->member, $this->club, $occurrence);

    expect($checkIn->strip_card_id)->toBeNull()
        ->and($checkIn->subscription_id)->toBeNull()
        ->and($checkIn->lesson_occurrence_id)->toBe($occurrence->id)
        ->and(StripCard::query()->first()->strips_remaining)->toBe(10);
});

test('a card with one strip can only cover one check-in', function () {
    StripCard::factory()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
        'strips_remaining' => 1,
    ]);

    app(CheckInMember::class)->handle($this->member, $this->club);

    expect(fn () => app(CheckInMember::class)->handle($this->member, $this->club))
        ->toThrow(CheckInException::class);

    expect(CheckIn::query()->count())->toBe(1);
});

test('expired or empty cards give no coverage', function () {
    StripCard::factory()->expired()->create(['club_id' => $this->club->id, 'user_id' => $this->member->id]);
    StripCard::factory()->empty()->create(['club_id' => $this->club->id, 'user_id' => $this->member->id]);

    expect(fn () => app(CheckInMember::class)->handle($this->member, $this->club))
        ->toThrow(CheckInException::class, __('No coverage: no unlimited subscription and no usable strip card.'));
});

test('double check-in for the same occurrence is rejected', function () {
    $lesson = Lesson::factory()->for($this->club)->create();
    $occurrence = LessonOccurrence::factory()->for($lesson)->create([
        'club_id' => $this->club->id,
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
    ]);
    StripCard::factory()->create(['club_id' => $this->club->id, 'user_id' => $this->member->id]);

    app(CheckInMember::class)->handle($this->member, $this->club, $occurrence);

    expect(fn () => app(CheckInMember::class)->handle($this->member, $this->club, $occurrence))
        ->toThrow(CheckInException::class);
});

test('non-members cannot check in', function () {
    $stranger = User::factory()->create();

    expect(fn () => app(CheckInMember::class)->handle($stranger, $this->club))
        ->toThrow(CheckInException::class);
});

test('staff can check a member in via the desk', function () {
    $staff = User::factory()->create();
    $this->club->addStaff($staff, StaffRole::Admin);

    StripCard::factory()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
    ]);

    Livewire::actingAs($staff)
        ->test(Desk::class, ['club' => $this->club])
        ->call('checkIn', $this->member->id);

    $checkIn = CheckIn::query()->firstOrFail();

    expect($checkIn->user_id)->toBe($this->member->id)
        ->and($checkIn->checked_in_by)->toBe($staff->id);
});

test('non-staff cannot open the check-in desk', function () {
    Livewire::actingAs($this->member)
        ->test(Desk::class, ['club' => $this->club])
        ->assertForbidden();
});
