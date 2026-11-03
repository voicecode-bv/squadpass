<?php

use App\Actions\Lessons\CancelEnrollment;
use App\Actions\Lessons\CancelOccurrence;
use App\Actions\Lessons\EnrollFixed;
use App\Actions\Lessons\EnrollSingle;
use App\Actions\Lessons\MarkAbsence;
use App\Enums\BillingInterval;
use App\Enums\CreditTransactionType;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Livewire\Clubs\Settings;
use App\Livewire\Members\Show;
use App\Livewire\Portal\Schedule;
use App\Livewire\Portal\Subscriptions;
use App\Models\Club;
use App\Models\CreditGrant;
use App\Models\CreditTransaction;
use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Credits\CreditLedger;
use Livewire\Livewire;

function policyOccurrence(Lesson $lesson, int $hoursAhead): LessonOccurrence
{
    return LessonOccurrence::factory()->for($lesson)->create([
        'club_id' => $lesson->club_id,
        'starts_at' => now()->addHours($hoursAhead),
        'ends_at' => now()->addHours($hoursAhead + 1),
    ]);
}

/**
 * @return list<string>
 */
function creditTimeline(User $member): array
{
    return CreditTransaction::query()
        ->where('user_id', $member->id)
        ->orderBy('id')
        ->get()
        ->map(fn (CreditTransaction $transaction) => $transaction->type->value.':'.$transaction->amount)
        ->all();
}

beforeEach(function () {
    $this->freezeTime();

    $this->club = Club::factory()->create(['cancellation_deadline_hours' => 3, 'credit_expiry_days' => 30]);
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);
    $this->lesson = Lesson::factory()->for($this->club)->create(['capacity' => 10]);

    $this->plan = SubscriptionPlan::factory()->for($this->club)->create(['credits' => 4]);
    $this->subscription = Subscription::factory()->active()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
        'subscription_plan_id' => $this->plan->id,
        'credits_remaining' => 0,
    ]);

    app(CreditLedger::class)->grant($this->subscription, 4);
});

test('new clubs default to a 3 hour cancellation deadline and 30 day credit expiry', function () {
    $club = Club::factory()->create()->refresh();

    expect($club->cancellation_deadline_hours)->toBe(3)
        ->and($club->credit_expiry_days)->toBe(30);
});

test('a member cancelling before the deadline gets the credit back', function () {
    $occurrence = policyOccurrence($this->lesson, 4);
    $enrollment = app(EnrollSingle::class)->handle($this->member, $occurrence);

    $outcome = app(CancelEnrollment::class)->handle($enrollment, $this->member);

    expect($outcome)->toBe(['refunded' => 1, 'forfeited' => 0])
        ->and($this->subscription->refresh()->credits_remaining)->toBe(4)
        ->and(creditTimeline($this->member))->toBe(['granted:4', 'consumed:-1', 'refunded:1']);
});

test('a member cancelling after the deadline frees the spot but keeps the credit spent', function () {
    $occurrence = policyOccurrence($this->lesson, 2);
    $enrollment = app(EnrollSingle::class)->handle($this->member, $occurrence);

    $outcome = app(CancelEnrollment::class)->handle($enrollment, $this->member);

    expect($outcome)->toBe(['refunded' => 0, 'forfeited' => 1])
        ->and($this->subscription->refresh()->credits_remaining)->toBe(3)
        ->and($occurrence->refresh()->booked_count)->toBe(0)
        ->and(creditTimeline($this->member))->toBe(['granted:4', 'consumed:-1', 'forfeited:0']);
});

test('staff removing a member after the deadline still refunds the credit', function () {
    $staff = User::factory()->create();
    $this->club->addStaff($staff, StaffRole::Owner);
    $occurrence = policyOccurrence($this->lesson, 1);
    $enrollment = app(EnrollSingle::class)->handle($this->member, $occurrence);

    app(CancelEnrollment::class)->handle($enrollment, $staff);

    expect($this->subscription->refresh()->credits_remaining)->toBe(4)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::Refunded)->value('actor_id'))->toBe($staff->id);
});

test('the deadline follows the club setting', function () {
    $this->club->update(['cancellation_deadline_hours' => 24]);
    $occurrence = policyOccurrence($this->lesson, 12);
    $enrollment = app(EnrollSingle::class)->handle($this->member, $occurrence);

    $outcome = app(CancelEnrollment::class)->handle($enrollment->fresh(), $this->member);

    expect($outcome['forfeited'])->toBe(1);
});

test('cancelling a recurring enrollment only forfeits the lessons inside the deadline', function () {
    policyOccurrence($this->lesson, 2);
    policyOccurrence($this->lesson, 24 * 7);
    $enrollment = app(EnrollFixed::class)->handle($this->member, $this->lesson);

    $outcome = app(CancelEnrollment::class)->handle($enrollment, $this->member);

    expect($outcome)->toBe(['refunded' => 1, 'forfeited' => 1])
        ->and($this->subscription->refresh()->credits_remaining)->toBe(3);
});

test('a late absence keeps the credit spent and rejoining that lesson is free', function () {
    $occurrence = policyOccurrence($this->lesson, 2);
    $enrollment = app(EnrollFixed::class)->handle($this->member, $this->lesson);

    $outcome = app(MarkAbsence::class)->handle($enrollment, $occurrence, $this->member);
    app(EnrollSingle::class)->handle($this->member, $occurrence->refresh());

    expect($outcome)->toBe(['refunded' => 0, 'forfeited' => 1])
        ->and($this->subscription->refresh()->credits_remaining)->toBe(3)
        ->and($occurrence->refresh()->booked_count)->toBe(1);
});

test('an absence in time refunds the credit', function () {
    $occurrence = policyOccurrence($this->lesson, 5);
    $enrollment = app(EnrollFixed::class)->handle($this->member, $this->lesson);

    $outcome = app(MarkAbsence::class)->handle($enrollment, $occurrence, $this->member);

    expect($outcome)->toBe(['refunded' => 1, 'forfeited' => 0])
        ->and($this->subscription->refresh()->credits_remaining)->toBe(4);
});

test('granted credits expire after the club expiry period', function () {
    $grant = CreditGrant::query()->where('subscription_id', $this->subscription->id)->sole();

    expect($grant->expires_at->toDateTimeString())->toBe(now()->addDays(30)->toDateTimeString());
});

test('credits never expire when the club has no expiry period', function () {
    $this->club->update(['credit_expiry_days' => null]);

    app(CreditLedger::class)->grant($this->subscription->fresh(), 2);

    expect(CreditGrant::query()->latest('id')->first()->expires_at)->toBeNull();
});

test('expired credits are removed from the balance and logged', function () {
    $this->travel(31)->days();
    $laterGrant = CreditGrant::factory()->create([
        'subscription_id' => $this->subscription->id,
        'amount' => 2,
    ]);
    $this->subscription->update(['credits_remaining' => 6]);

    $this->artisan('credits:expire')->assertSuccessful();

    expect($this->subscription->refresh()->credits_remaining)->toBe(2)
        ->and($laterGrant->refresh()->remaining)->toBe(2)
        ->and(creditTimeline($this->member))->toBe(['granted:4', 'expired:-4']);
});

test('the monthly grant adds credits on top of the unexpired leftover and moves to the next month', function () {
    $this->travelTo('2026-03-31 03:00');
    $this->subscription->update(['starts_at' => '2026-01-31', 'next_credits_at' => '2026-03-31']);

    $this->artisan('credits:grant')->assertSuccessful();

    $this->subscription->refresh();
    expect($this->subscription->credits_remaining)->toBe(8)
        ->and($this->subscription->next_credits_at->toDateString())->toBe('2026-04-30');
});

test('a yearly plan still gets its credits every month', function () {
    $this->travelTo('2026-01-15 03:00');
    $this->plan->update(['interval' => BillingInterval::Yearly]);
    $this->subscription->update(['starts_at' => '2026-01-15', 'next_billing_at' => '2027-01-15', 'next_credits_at' => '2026-02-15']);

    $this->travelTo('2026-02-15 03:00');
    $this->artisan('credits:grant');
    $this->travelTo('2026-03-15 03:00');
    $this->artisan('credits:grant');

    $this->subscription->refresh();
    expect(CreditGrant::query()->where('subscription_id', $this->subscription->id)->count())->toBe(3)
        ->and($this->subscription->next_credits_at->toDateString())->toBe('2026-04-15')
        ->and($this->subscription->next_billing_at->toDateString())->toBe('2027-01-15');
});

test('the monthly grant runs only once per month', function () {
    $this->subscription->update(['next_credits_at' => today()]);

    $this->artisan('credits:grant');
    $this->artisan('credits:grant');

    expect($this->subscription->refresh()->credits_remaining)->toBe(8);
});

test('subscriptions without access get no monthly credits', function (string $state) {
    $this->subscription->update([
        'next_credits_at' => today(),
        ...match ($state) {
            'past due' => ['status' => SubscriptionStatus::PastDue],
            'ended' => ['status' => SubscriptionStatus::Cancelled, 'ends_at' => today()->subDay()],
        },
    ]);

    $this->artisan('credits:grant');

    expect($this->subscription->refresh()->credits_remaining)->toBe(4);
})->with(['past due', 'ended']);

test('credits are spent from the grant that expires first', function () {
    $laterGrant = CreditGrant::factory()->create([
        'subscription_id' => $this->subscription->id,
        'amount' => 2,
        'expires_at' => now()->addDays(60),
    ]);
    $this->subscription->update(['credits_remaining' => 6]);

    app(EnrollSingle::class)->handle($this->member, policyOccurrence($this->lesson, 48));

    expect($laterGrant->refresh()->remaining)->toBe(2)
        ->and(CreditGrant::query()->whereKeyNot($laterGrant->id)->sole()->remaining)->toBe(3);
});

test('a refund to an expired grant does not return the credit', function () {
    $enrollment = app(EnrollSingle::class)->handle($this->member, policyOccurrence($this->lesson, 24 * 40));
    $this->travel(31)->days();
    $this->artisan('credits:expire');

    $outcome = app(CancelEnrollment::class)->handle($enrollment, $this->member);

    expect($outcome)->toBe(['refunded' => 0, 'forfeited' => 0])
        ->and($this->subscription->refresh()->credits_remaining)->toBe(0)
        ->and(creditTimeline($this->member))->toBe(['granted:4', 'consumed:-1', 'expired:-3', 'expired:0']);
});

test('the portal shows the free cancellation deadline and warns once it passed', function () {
    $inTime = policyOccurrence($this->lesson, 5);
    $late = policyOccurrence($this->lesson, 2);
    app(EnrollSingle::class)->handle($this->member, $inTime);
    app(EnrollSingle::class)->handle($this->member, $late);

    $component = Livewire::actingAs($this->member)->test(Schedule::class);

    $component->call('showOccurrence', $inTime->id)
        ->assertSeeHtml('data-test="free-cancellation"')
        ->assertDontSeeHtml('data-test="late-cancellation"');
    $component->call('showOccurrence', $late->id)
        ->assertSeeHtml('data-test="late-cancellation"');
});

test('the portal toast tells the member the credit was not refunded after the deadline', function () {
    $occurrence = policyOccurrence($this->lesson, 2);
    app(EnrollSingle::class)->handle($this->member, $occurrence);

    Livewire::actingAs($this->member)
        ->test(Schedule::class)
        ->call('cancelSingle', $occurrence->id)
        ->assertDispatched('toast-show', fn (string $name, array $params) => $params['dataset']['variant'] === 'warning');
});

test('staff see the credit timeline of a member', function () {
    $owner = User::factory()->create();
    $this->club->addStaff($owner, StaffRole::Owner);
    $membership = $this->club->memberships()->where('user_id', $this->member->id)->sole();
    $this->lesson->update(['name' => 'Kickboksen gevorderd']);
    app(EnrollSingle::class)->handle($this->member, policyOccurrence($this->lesson, 48));

    Livewire::actingAs($owner)
        ->withQueryParams(['tab' => 'credits'])
        ->test(Show::class, ['club' => $this->club, 'membership' => $membership])
        ->assertSee(__('Credits added'))
        ->assertSee(__('Credits used'))
        ->assertSee('Kickboksen gevorderd');
});

test('the credit timeline only shows the activity of this member in this club', function () {
    $owner = User::factory()->create();
    $this->club->addStaff($owner, StaffRole::Owner);
    $membership = $this->club->memberships()->where('user_id', $this->member->id)->sole();
    $otherSubscription = Subscription::factory()->active()->create(['credits_remaining' => 0]);
    app(CreditLedger::class)->grant($otherSubscription, 9);

    Livewire::actingAs($owner)
        ->test(Show::class, ['club' => $this->club, 'membership' => $membership])
        ->assertSet('creditTransactions', fn ($transactions) => $transactions->pluck('amount')->all() === [4]);
});

test('an owner can set the cancellation deadline and credit expiry', function () {
    $owner = User::factory()->create();
    $this->club->addStaff($owner, StaffRole::Owner);

    Livewire::actingAs($owner)
        ->test(Settings::class, ['club' => $this->club])
        ->set('cancellation_deadline_hours', 12)
        ->set('credits_expire', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->club->refresh()->cancellation_deadline_hours)->toBe(12)
        ->and($this->club->credit_expiry_days)->toBeNull();
});

test('the credit policy settings are validated', function (string $field, mixed $value) {
    $owner = User::factory()->create();
    $this->club->addStaff($owner, StaffRole::Owner);

    Livewire::actingAs($owner)
        ->test(Settings::class, ['club' => $this->club])
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($field)
        ->assertSet('tab', 'lessons');
})->with([
    'negative deadline' => ['cancellation_deadline_hours', -1],
    'deadline over a week' => ['cancellation_deadline_hours', 169],
    'zero expiry' => ['credit_expiry_days', 0],
]);

test('the club cancelling a lesson releases every booking and refunds the credits, also inside the deadline', function () {
    $owner = User::factory()->create();
    $this->club->addStaff($owner, StaffRole::Owner);
    $occurrence = policyOccurrence($this->lesson, 1);
    $fixedMember = User::factory()->create();
    $this->club->addMember($fixedMember);
    $fixedSubscription = Subscription::factory()->active()->create([
        'club_id' => $this->club->id,
        'user_id' => $fixedMember->id,
        'subscription_plan_id' => $this->plan->id,
        'credits_remaining' => 0,
    ]);
    app(CreditLedger::class)->grant($fixedSubscription, 4);
    app(EnrollSingle::class)->handle($this->member, $occurrence);
    $fixedEnrollment = app(EnrollFixed::class)->handle($fixedMember, $this->lesson);

    $released = app(CancelOccurrence::class)->handle($occurrence, $owner);

    expect($released)->toBe(2)
        ->and($occurrence->refresh()->isCancelled())->toBeTrue()
        ->and($occurrence->booked_count)->toBe(0)
        ->and($this->subscription->refresh()->credits_remaining)->toBe(4)
        ->and($fixedSubscription->refresh()->credits_remaining)->toBe(4)
        ->and(LessonEnrollment::query()->where('user_id', $this->member->id)->exists())->toBeFalse()
        ->and($fixedEnrollment->absences()->where('lesson_occurrence_id', $occurrence->id)->exists())->toBeTrue();
});

test('cancelling a lesson twice refunds the credits only once', function () {
    $owner = User::factory()->create();
    $this->club->addStaff($owner, StaffRole::Owner);
    $occurrence = policyOccurrence($this->lesson, 48);
    app(EnrollSingle::class)->handle($this->member, $occurrence);
    app(CancelOccurrence::class)->handle($occurrence, $owner);

    $released = app(CancelOccurrence::class)->handle($occurrence, $owner);

    expect($released)->toBe(0)
        ->and($this->subscription->refresh()->credits_remaining)->toBe(4);
});

test('a member sees their own credit history in the portal', function () {
    $this->lesson->update(['name' => 'Ochtendyoga']);
    app(EnrollSingle::class)->handle($this->member, policyOccurrence($this->lesson, 48));
    $otherSubscription = Subscription::factory()->active()->create(['credits_remaining' => 0]);
    app(CreditLedger::class)->grant($otherSubscription, 9);

    Livewire::actingAs($this->member)
        ->test(Subscriptions::class)
        ->assertSeeHtml('data-test="credit-timeline"')
        ->assertSee('Ochtendyoga')
        ->assertSee(__('By you'))
        ->assertSet('creditTransactions', fn ($transactions) => $transactions->pluck('amount')->all() === [-1, 4]);
});
