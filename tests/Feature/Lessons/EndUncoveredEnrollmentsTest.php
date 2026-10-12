<?php

use App\Actions\Lessons\EnrollFixed;
use App\Enums\SubscriptionStatus;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);

    $this->lesson = Lesson::factory()->for($this->club)->create(['capacity' => 10]);
    $this->occurrence = LessonOccurrence::factory()->for($this->lesson)->create([
        'club_id' => $this->club->id,
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHour(),
    ]);
});

function subscribeFor(Club $club, User $member, int $credits = 10, array $attributes = []): Subscription
{
    $plan = SubscriptionPlan::factory()->for($club)->create(['credits' => $credits]);

    return Subscription::factory()->active()->create([
        'club_id' => $club->id,
        'user_id' => $member->id,
        'subscription_plan_id' => $plan->id,
        'credits_remaining' => $credits,
        ...$attributes,
    ]);
}

test('a recurring enrollment ends when the subscription has expired', function () {
    $subscription = subscribeFor($this->club, $this->member);

    app(EnrollFixed::class)->handle($this->member, $this->lesson);

    expect($this->occurrence->refresh()->booked_count)->toBe(1);

    $subscription->update([
        'status' => SubscriptionStatus::Cancelled,
        'ends_at' => today()->subDay(),
    ]);

    $this->artisan('enrollments:end-uncovered')->assertSuccessful();

    expect(LessonEnrollment::query()->count())->toBe(0)
        ->and($this->occurrence->refresh()->booked_count)->toBe(0);
});

test('a recurring enrollment ends when the subscription is overdue', function () {
    $subscription = subscribeFor($this->club, $this->member);

    app(EnrollFixed::class)->handle($this->member, $this->lesson);

    $subscription->update(['status' => SubscriptionStatus::PastDue]);

    $this->artisan('enrollments:end-uncovered')->assertSuccessful();

    expect(LessonEnrollment::query()->count())->toBe(0);
});

test('a running subscription keeps the recurring enrollment', function () {
    subscribeFor($this->club, $this->member);

    app(EnrollFixed::class)->handle($this->member, $this->lesson);

    $this->artisan('enrollments:end-uncovered')->assertSuccessful();

    expect(LessonEnrollment::query()->count())->toBe(1)
        ->and($this->occurrence->refresh()->booked_count)->toBe(1);
});

test('a cancelled subscription keeps the enrollment until the paid period ends', function () {
    $subscription = subscribeFor($this->club, $this->member);

    app(EnrollFixed::class)->handle($this->member, $this->lesson);

    $subscription->update([
        'status' => SubscriptionStatus::Cancelled,
        'ends_at' => today()->addWeek(),
    ]);

    $this->artisan('enrollments:end-uncovered')->assertSuccessful();

    expect(LessonEnrollment::query()->count())->toBe(1);
});

test('a member who started a new subscription keeps the enrollment', function () {
    $old = subscribeFor($this->club, $this->member);

    app(EnrollFixed::class)->handle($this->member, $this->lesson);

    $old->update(['status' => SubscriptionStatus::Cancelled, 'ends_at' => today()->subDay()]);
    subscribeFor($this->club, $this->member);

    $this->artisan('enrollments:end-uncovered')->assertSuccessful();

    expect(LessonEnrollment::query()->count())->toBe(1);
});

test('a member without a subscription keeps the enrollment and pays per visit', function () {
    app(EnrollFixed::class)->handle($this->member, $this->lesson);

    expect(LessonEnrollment::query()->first()->subscription_id)->toBeNull();

    $this->artisan('enrollments:end-uncovered')->assertSuccessful();

    expect(LessonEnrollment::query()->count())->toBe(1)
        ->and($this->occurrence->refresh()->booked_count)->toBe(1);
});

test('enrollments of another club are left alone', function () {
    $otherClub = Club::factory()->create();
    $otherMember = User::factory()->create();
    $otherClub->addMember($otherMember);
    $otherLesson = Lesson::factory()->for($otherClub)->create();
    LessonOccurrence::factory()->for($otherLesson)->create([
        'club_id' => $otherClub->id,
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHour(),
    ]);

    subscribeFor($otherClub, $otherMember);
    app(EnrollFixed::class)->handle($otherMember, $otherLesson);

    $expired = subscribeFor($this->club, $this->member);
    app(EnrollFixed::class)->handle($this->member, $this->lesson);
    $expired->update(['status' => SubscriptionStatus::Cancelled, 'ends_at' => today()->subDay()]);

    $this->artisan('enrollments:end-uncovered')->assertSuccessful();

    expect(LessonEnrollment::query()->count())->toBe(1)
        ->and(LessonEnrollment::query()->first()->club_id)->toBe($otherClub->id);
});
