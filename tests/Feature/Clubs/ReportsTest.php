<?php

use App\Enums\BillingInterval;
use App\Enums\MembershipStatus;
use App\Enums\StaffPermission;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Livewire\Clubs\Reports;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\EnrollmentAbsence;
use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\StripCardPlan;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-15 12:00'));

    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

function reportsStaffWith(Club $club, array $permissions): User
{
    $staff = User::factory()->create();
    $club->addStaff($staff, StaffRole::Admin, $permissions);

    return $staff;
}

test('staff without the reports permission cannot open reports', function () {
    $this->actingAs(reportsStaffWith($this->club, [StaffPermission::Members]))
        ->get(route('clubs.reports', $this->club))
        ->assertForbidden();
});

test('staff with reports but without billing do not see revenue', function () {
    Payment::factory()->for($this->club)->paid()->create(['amount_cents' => 4321]);

    $this->actingAs(reportsStaffWith($this->club, [StaffPermission::Reports]))
        ->get(route('clubs.reports', $this->club))
        ->assertOk()
        ->assertDontSee(__('Revenue per month'))
        ->assertDontSee(__('Monthly recurring revenue'));
});

test('revenue per month is split by what was paid for', function () {
    $paidAt = ['paid_at' => Carbon::parse('2026-10-10')];
    Payment::factory()->for($this->club)->paid()->create(['amount_cents' => 3000, 'payable_type' => Subscription::class, ...$paidAt]);
    Payment::factory()->for($this->club)->paid()->create(['amount_cents' => 2000, 'payable_type' => StripCardPlan::class, ...$paidAt]);
    Payment::factory()->for($this->club)->paid()->create(['amount_cents' => 1000, 'payable_type' => LessonEnrollment::class, 'paid_at' => Carbon::parse('2026-11-01')]);
    Payment::factory()->for($this->club)->paid()->create(['amount_cents' => 9999, 'paid_at' => Carbon::parse('2025-01-01')]);
    Payment::factory()->paid()->create(['amount_cents' => 7777, ...$paidAt]);

    $revenue = Livewire::actingAs($this->owner)
        ->test(Reports::class, ['club' => $this->club])
        ->set('months', 3)
        ->instance()->revenue;

    expect($revenue['total'])->toBe([0, 5000, 1000])
        ->and($revenue['subscriptions'])->toBe(3000)
        ->and($revenue['strip_cards'])->toBe(2000)
        ->and($revenue['trial_lessons'])->toBe(1000);
});

test('monthly recurring revenue spreads yearly plans and skips cancelled subscriptions', function () {
    $monthly = SubscriptionPlan::factory()->for($this->club)->create(['price_cents' => 4000, 'interval' => BillingInterval::Monthly]);
    $yearly = SubscriptionPlan::factory()->for($this->club)->create(['price_cents' => 36000, 'interval' => BillingInterval::Yearly]);
    Subscription::factory()->active()->for($this->club)->create(['subscription_plan_id' => $monthly->id]);
    Subscription::factory()->for($this->club)->create(['subscription_plan_id' => $monthly->id, 'status' => SubscriptionStatus::PastDue]);
    Subscription::factory()->active()->for($this->club)->create(['subscription_plan_id' => $yearly->id]);
    Subscription::factory()->for($this->club)->create(['subscription_plan_id' => $monthly->id, 'status' => SubscriptionStatus::Cancelled]);

    $component = Livewire::actingAs($this->owner)->test(Reports::class, ['club' => $this->club]);

    expect($component->instance()->monthlyRecurringRevenueCents)->toBe(11000);
});

test('churn divides cancellations by the subscriptions running at the start of the month', function () {
    Subscription::factory()->active()->for($this->club)->count(3)->create(['starts_at' => '2026-09-01']);
    Subscription::factory()->for($this->club)->create([
        'status' => SubscriptionStatus::Cancelled,
        'starts_at' => '2026-09-01',
        'cancelled_at' => Carbon::parse('2026-11-05'),
    ]);

    $churn = Livewire::actingAs($this->owner)
        ->test(Reports::class, ['club' => $this->club])
        ->set('months', 3)
        ->instance()->churn;

    expect($churn['cancellations'])->toBe([0, 0, 1])
        ->and($churn['rates'])->toBe([0.0, 0.0, 25.0]);
});

test('trial conversion counts trials in the period and those that became members', function () {
    Membership::factory()->for($this->club)->create(['status' => MembershipStatus::Trial, 'trial_started_at' => '2026-11-01', 'joined_at' => '2026-11-01']);
    Membership::factory()->for($this->club)->create(['status' => MembershipStatus::Active, 'trial_started_at' => '2026-10-01', 'joined_at' => '2026-10-08']);
    Membership::factory()->for($this->club)->ended()->create(['trial_started_at' => '2026-10-01', 'joined_at' => '2026-10-01']);
    Membership::factory()->for($this->club)->create(['status' => MembershipStatus::Active, 'trial_started_at' => '2025-01-01', 'joined_at' => '2025-01-08']);

    $trials = Livewire::actingAs($this->owner)
        ->test(Reports::class, ['club' => $this->club])
        ->set('months', 3)
        ->instance()->trialConversion;

    expect($trials)->toBe(['trials' => 3, 'converted' => 1]);
});

test('attendance shows occupancy, no-shows and late cancellations per lesson', function () {
    $lesson = Lesson::factory()->for($this->club)->create(['name' => 'Bootcamp', 'capacity' => 10]);
    $occurrence = LessonOccurrence::factory()->for($lesson)->create([
        'club_id' => $this->club->id,
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDays(2)->addHour(),
        'booked_count' => 8,
    ]);
    LessonOccurrence::factory()->for($lesson)->create([
        'club_id' => $this->club->id,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
        'booked_count' => 10,
    ]);
    CheckIn::factory()->count(6)->create(['club_id' => $this->club->id, 'lesson_occurrence_id' => $occurrence->id, 'checked_in_at' => now()->subDays(2)]);
    $enrollment = LessonEnrollment::factory()->create(['club_id' => $this->club->id, 'lesson_id' => $lesson->id]);
    EnrollmentAbsence::query()->create(['lesson_enrollment_id' => $enrollment->id, 'lesson_occurrence_id' => $occurrence->id, 'credit_forfeited' => true]);

    $attendance = Livewire::actingAs($this->owner)
        ->test(Reports::class, ['club' => $this->club])
        ->assertSee('Bootcamp')
        ->instance()->lessonAttendance;

    expect($attendance)->toHaveCount(1)
        ->and($attendance[0])->toMatchArray([
            'occurrences' => 1,
            'occupancy' => 80.0,
            'check_ins' => 6,
            'no_show_rate' => 25.0,
            'late_cancellations' => 1,
        ]);
});

test('no-shows stay empty for a club that does not check people in', function () {
    $lesson = Lesson::factory()->for($this->club)->create(['capacity' => 10]);
    LessonOccurrence::factory()->for($lesson)->create([
        'club_id' => $this->club->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
        'booked_count' => 5,
    ]);

    $attendance = Livewire::actingAs($this->owner)->test(Reports::class, ['club' => $this->club])->instance()->lessonAttendance;

    expect($attendance[0]['no_show_rate'])->toBeNull();
});
