<?php

use App\Actions\Lessons\CancelEnrollment;
use App\Actions\Lessons\EnrollFixed;
use App\Actions\Lessons\EnrollSingle;
use App\Actions\Lessons\MarkAbsence;
use App\Actions\Payments\ChargeSubscription;
use App\Exceptions\EnrollmentException;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\LessonOccurrence;
use App\Models\MollieCustomer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Lessons\OccurrenceGenerator;
use App\Services\Mollie\MollieGateway;
use Tests\Fakes\FakeMollieGateway;

function creditOccurrence(Lesson $lesson, int $daysAhead): LessonOccurrence
{
    return LessonOccurrence::factory()->for($lesson)->create([
        'club_id' => $lesson->club_id,
        'starts_at' => now()->addDays($daysAhead)->setTime(19, 0),
        'ends_at' => now()->addDays($daysAhead)->setTime(20, 0),
    ]);
}

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);
    $this->lesson = Lesson::factory()->for($this->club)->create(['capacity' => 10]);

    $this->plan = SubscriptionPlan::factory()->for($this->club)->create(['credits' => 4]);
    $this->subscription = Subscription::factory()->active()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
        'subscription_plan_id' => $this->plan->id,
        'credits_remaining' => 4,
    ]);
});

test('a one-time reservation consumes one credit', function () {
    $occurrence = creditOccurrence($this->lesson, 3);

    $enrollment = app(EnrollSingle::class)->handle($this->member, $occurrence);

    expect($this->subscription->refresh()->credits_remaining)->toBe(3)
        ->and($enrollment->credits_used)->toBe(1)
        ->and($enrollment->subscription_id)->toBe($this->subscription->id);
});

test('a reservation fails without credits and releases the spot', function () {
    $this->subscription->update(['credits_remaining' => 0]);
    $occurrence = creditOccurrence($this->lesson, 3);

    expect(fn () => app(EnrollSingle::class)->handle($this->member, $occurrence))
        ->toThrow(EnrollmentException::class, __('Your subscription has no credits left for this period.'));

    expect($occurrence->refresh()->booked_count)->toBe(0);
});

test('an unlimited subscription consumes no credits', function () {
    $this->subscription->update(['credits_remaining' => null]);
    $occurrence = creditOccurrence($this->lesson, 3);

    $enrollment = app(EnrollSingle::class)->handle($this->member, $occurrence);

    expect($this->subscription->refresh()->credits_remaining)->toBeNull()
        ->and($enrollment->credits_used)->toBe(0);
});

test('a member without subscription can still reserve without credits', function () {
    $this->subscription->delete();
    $occurrence = creditOccurrence($this->lesson, 3);

    $enrollment = app(EnrollSingle::class)->handle($this->member, $occurrence);

    expect($enrollment->credits_used)->toBe(0)
        ->and($enrollment->subscription_id)->toBeNull();
});

test('a recurring enrollment consumes one credit per future occurrence', function () {
    creditOccurrence($this->lesson, 3);
    creditOccurrence($this->lesson, 10);
    creditOccurrence($this->lesson, 17);

    $enrollment = app(EnrollFixed::class)->handle($this->member, $this->lesson);

    expect($this->subscription->refresh()->credits_remaining)->toBe(1)
        ->and($enrollment->credits_used)->toBe(3);
});

test('a recurring enrollment fails and rolls back when credits are insufficient', function () {
    $this->subscription->update(['credits_remaining' => 2]);
    $first = creditOccurrence($this->lesson, 3);
    creditOccurrence($this->lesson, 10);
    creditOccurrence($this->lesson, 17);

    expect(fn () => app(EnrollFixed::class)->handle($this->member, $this->lesson))
        ->toThrow(EnrollmentException::class);

    expect($this->subscription->refresh()->credits_remaining)->toBe(2)
        ->and($first->refresh()->booked_count)->toBe(0)
        ->and($this->lesson->enrollments()->count())->toBe(0);
});

test('cancelling a reservation refunds the credits for future lessons', function () {
    $occurrence = creditOccurrence($this->lesson, 3);
    $enrollment = app(EnrollSingle::class)->handle($this->member, $occurrence);

    app(CancelEnrollment::class)->handle($enrollment);

    expect($this->subscription->refresh()->credits_remaining)->toBe(4);
});

test('an absence refunds one credit and rejoining consumes it again', function () {
    $occurrence = creditOccurrence($this->lesson, 3);
    creditOccurrence($this->lesson, 10);

    $enrollment = app(EnrollFixed::class)->handle($this->member, $this->lesson);
    expect($this->subscription->refresh()->credits_remaining)->toBe(2);

    app(MarkAbsence::class)->handle($enrollment, $occurrence->refresh());
    expect($this->subscription->refresh()->credits_remaining)->toBe(3)
        ->and($enrollment->refresh()->credits_used)->toBe(1);

    app(EnrollSingle::class)->handle($this->member, $occurrence->refresh());
    expect($this->subscription->refresh()->credits_remaining)->toBe(2)
        ->and($enrollment->refresh()->credits_used)->toBe(2);
});

test('the recurring charge refills the credits for the new period', function () {
    $this->app->instance(MollieGateway::class, $gateway = new FakeMollieGateway);

    $this->club->update(['mollie_api_key' => 'test_key']);
    MollieCustomer::create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
        'mollie_customer_id' => 'cst_x',
    ]);

    $this->subscription->update(['credits_remaining' => 0, 'next_billing_at' => today()]);

    app(ChargeSubscription::class)->handle($this->subscription->refresh());

    expect($this->subscription->refresh()->credits_remaining)->toBe(4);
});

test('a newly generated occurrence consumes a credit for a recurring member', function () {
    $this->lesson->update([
        'day_of_week' => now($this->club->timezone)->addDays(2)->isoWeekday(),
        'start_time' => '19:00:00',
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addWeeks(3)->toDateString(),
    ]);

    app(EnrollFixed::class)->handle($this->member, $this->lesson);

    // Nothing was generated yet, so the enrollment claimed no spots and cost nothing.
    expect($this->subscription->refresh()->credits_remaining)->toBe(4);

    app(OccurrenceGenerator::class)->generateForLesson($this->lesson->refresh());

    $occurrences = $this->lesson->occurrences()->orderBy('starts_at')->get();

    expect($occurrences)->toHaveCount(3)
        ->and($occurrences->pluck('booked_count')->unique()->all())->toBe([1])
        ->and($this->subscription->refresh()->credits_remaining)->toBe(1);
});

test('a recurring member out of credits is not booked into new occurrences', function () {
    $this->subscription->update(['credits_remaining' => 1]);
    $this->lesson->update([
        'day_of_week' => now($this->club->timezone)->addDays(2)->isoWeekday(),
        'start_time' => '19:00:00',
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addWeeks(3)->toDateString(),
    ]);

    app(EnrollFixed::class)->handle($this->member, $this->lesson);

    app(OccurrenceGenerator::class)->generateForLesson($this->lesson->refresh());

    $counts = $this->lesson->occurrences()->orderBy('starts_at')->pluck('booked_count');

    expect($counts->all())->toBe([1, 0, 0])
        ->and($this->subscription->refresh()->credits_remaining)->toBe(0);
});

test('an unlimited subscription books new occurrences without credits', function () {
    $this->subscription->update(['credits_remaining' => null]);
    $this->lesson->update([
        'day_of_week' => now($this->club->timezone)->addDays(2)->isoWeekday(),
        'start_time' => '19:00:00',
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addWeeks(3)->toDateString(),
    ]);

    app(EnrollFixed::class)->handle($this->member, $this->lesson);

    app(OccurrenceGenerator::class)->generateForLesson($this->lesson->refresh());

    expect($this->lesson->occurrences()->pluck('booked_count')->unique()->all())->toBe([1])
        ->and($this->subscription->refresh()->credits_remaining)->toBeNull();
});

test('generating twice does not charge the same week twice', function () {
    $this->lesson->update([
        'day_of_week' => now($this->club->timezone)->addDays(2)->isoWeekday(),
        'start_time' => '19:00:00',
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addWeeks(3)->toDateString(),
    ]);

    app(EnrollFixed::class)->handle($this->member, $this->lesson);

    app(OccurrenceGenerator::class)->generateForLesson($this->lesson->refresh());
    app(OccurrenceGenerator::class)->generateForLesson($this->lesson->refresh());

    expect($this->lesson->occurrences()->pluck('booked_count')->unique()->all())->toBe([1])
        ->and($this->subscription->refresh()->credits_remaining)->toBe(1);
});
