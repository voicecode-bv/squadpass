<?php

use App\Actions\CheckIns\CheckInMember;
use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Livewire\Clubs\TrialLesson;
use App\Livewire\Members\Index as MembersIndex;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\NewTrialLead;
use App\Notifications\TrialLessonBooked;
use App\Services\Mollie\MollieGateway;
use App\Services\SpamProtection;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Fakes\FakeMollieGateway;

function trialOccurrence(Club $club, array $lessonAttributes = []): LessonOccurrence
{
    $lesson = Lesson::factory()->for($club)->create(['capacity' => 10, 'trial_enabled' => true, ...$lessonAttributes]);

    return LessonOccurrence::factory()->for($lesson)->create([
        'club_id' => $club->id,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
    ]);
}

/**
 * The trial form filled in for an occurrence, submitted after a human-like pause.
 */
function filledTrialForm(Club $club, LessonOccurrence $occurrence, string $email = 'sam@example.com'): Testable
{
    $component = Livewire::test(TrialLesson::class, ['club' => $club])
        ->set('occurrenceId', $occurrence->id)
        ->set('first_name', 'Sam')
        ->set('last_name', 'de Vries')
        ->set('email', $email)
        ->set('phone', '0612345678');

    test()->travel(SpamProtection::MINIMUM_SECONDS)->seconds();

    return $component;
}

beforeEach(function () {
    $this->club = Club::factory()->create(['trial_lessons_enabled' => true]);
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

test('a visitor books a free trial lesson and gets a trial membership', function () {
    Notification::fake();
    $occurrence = trialOccurrence($this->club);

    filledTrialForm($this->club, $occurrence)
        ->call('book')
        ->assertHasNoErrors()
        ->assertSet('booked', true);

    $visitor = User::query()->where('email', 'sam@example.com')->sole();
    $membership = Membership::query()->where('user_id', $visitor->id)->sole();

    expect($membership->status)->toBe(MembershipStatus::Trial)
        ->and($membership->phone)->toBe('0612345678')
        ->and($occurrence->refresh()->booked_count)->toBe(1)
        ->and(LessonEnrollment::query()->where('user_id', $visitor->id)->where('lesson_occurrence_id', $occurrence->id)->exists())->toBeTrue()
        ->and($this->club->activeMemberCount())->toBe(0);
    Notification::assertSentTo($visitor, TrialLessonBooked::class, fn (TrialLessonBooked $notification) => $notification->accountCreated);
    Notification::assertSentTo($this->owner, NewTrialLead::class);
});

test('a full lesson cannot be booked as a trial lesson', function () {
    $occurrence = trialOccurrence($this->club, ['capacity' => 1]);
    $occurrence->claimSpot();

    filledTrialForm($this->club, $occurrence)
        ->call('book')
        ->assertNotFound();

    expect(User::query()->where('email', 'sam@example.com')->exists())->toBeFalse();
});

test('a visitor gets only one trial lesson per club', function () {
    $occurrence = trialOccurrence($this->club);
    filledTrialForm($this->club, $occurrence)->call('book');

    filledTrialForm($this->club, $occurrence)
        ->call('book')
        ->assertHasErrors('occurrenceId');

    expect($occurrence->refresh()->booked_count)->toBe(1);
});

test('an active member is sent to log in instead of booking a trial lesson', function () {
    $member = User::factory()->create(['email' => 'member@example.com']);
    $this->club->addMember($member);
    $occurrence = trialOccurrence($this->club);

    filledTrialForm($this->club, $occurrence, 'member@example.com')
        ->call('book')
        ->assertHasErrors(['occurrenceId' => __('You are already a member of this club. Log in to book lessons.')]);

    expect($occurrence->refresh()->booked_count)->toBe(0);
});

test('lessons not offered as trial and clubs without trial lessons cannot be booked', function () {
    $occurrence = trialOccurrence($this->club, ['trial_enabled' => false]);

    filledTrialForm($this->club, $occurrence)->call('book')->assertNotFound();

    $closedClub = Club::factory()->create(['trial_lessons_enabled' => false]);
    $this->get(route('clubs.trial', $closedClub))->assertNotFound();
});

test('a trial submitted with the honeypot filled in is rejected', function () {
    $occurrence = trialOccurrence($this->club);

    filledTrialForm($this->club, $occurrence)
        ->set('website', 'https://spam.example')
        ->call('book')
        ->assertHasErrors(SpamProtection::ERROR_KEY);

    expect($occurrence->refresh()->booked_count)->toBe(0);
});

test('trial bookings are rate limited per visitor', function () {
    $occurrence = trialOccurrence($this->club);

    foreach (range(1, TrialLesson::MAX_ATTEMPTS_PER_MINUTE) as $attempt) {
        filledTrialForm($this->club, $occurrence, "visitor{$attempt}@example.com")->set('website', 'spam')->call('book');
    }

    filledTrialForm($this->club, $occurrence, 'late@example.com')
        ->call('book')
        ->assertHasErrors('email');

    expect($occurrence->refresh()->booked_count)->toBe(0);
});

describe('paid trial lessons', function () {
    beforeEach(function () {
        $this->gateway = new FakeMollieGateway;
        $this->app->instance(MollieGateway::class, $this->gateway);
    });

    test('a paid trial goes to the Mollie checkout and is confirmed once paid', function () {
        Notification::fake();
        $club = Club::factory()->withMollieKey()->create(['trial_lessons_enabled' => true]);
        $occurrence = trialOccurrence($club, ['trial_price_cents' => 1250]);

        filledTrialForm($club, $occurrence)
            ->call('book')
            ->assertRedirect('https://mollie.test/checkout/tr_fake2');

        $payment = Payment::query()->sole();
        expect($payment->purpose())->toBe('trial_lesson')
            ->and($payment->amount_cents)->toBe(1250)
            ->and($occurrence->refresh()->booked_count)->toBe(1);
        Notification::assertNothingSentTo($payment->user);

        $this->gateway->setPaymentStatus($payment->mollie_payment_id, PaymentStatus::Paid);
        $this->post(route('webhooks.mollie', $club), ['id' => $payment->mollie_payment_id]);

        Notification::assertSentTo($payment->user, TrialLessonBooked::class);
    });

    test('a failed trial payment frees the spot and the trial membership', function () {
        $club = Club::factory()->withMollieKey()->create(['trial_lessons_enabled' => true]);
        $occurrence = trialOccurrence($club, ['trial_price_cents' => 1250]);
        filledTrialForm($club, $occurrence)->call('book');

        $payment = Payment::query()->sole();
        $this->gateway->setPaymentStatus($payment->mollie_payment_id, PaymentStatus::Failed);
        $this->post(route('webhooks.mollie', $club), ['id' => $payment->mollie_payment_id]);

        expect($occurrence->refresh()->booked_count)->toBe(0)
            ->and(LessonEnrollment::query()->count())->toBe(0)
            ->and(Membership::query()->where('club_id', $club->id)->count())->toBe(0);
    });

    test('a paid trial is not offered when the club cannot take online payments', function () {
        $occurrence = trialOccurrence(Club::factory()->create(['trial_lessons_enabled' => true]), ['trial_price_cents' => 1250]);

        filledTrialForm($occurrence->club, $occurrence)->call('book')->assertNotFound();
    });
});

test('staff convert a trial member to an active member', function () {
    $occurrence = trialOccurrence($this->club);
    filledTrialForm($this->club, $occurrence)->call('book');
    $membership = Membership::query()->where('status', MembershipStatus::Trial)->sole();

    $this->travel(3)->days();

    Livewire::actingAs($this->owner)
        ->test(MembersIndex::class, ['club' => $this->club])
        ->call('updateStatus', $membership->id, 'active');

    expect($membership->refresh()->status)->toBe(MembershipStatus::Active)
        ->and($membership->joined_at->toDateString())->toBe(today()->toDateString())
        ->and($membership->trial_started_at)->not->toBeNull();
});

test('a trial member is checked in for their trial lesson only', function () {
    $occurrence = trialOccurrence($this->club);
    filledTrialForm($this->club, $occurrence)->call('book');
    $visitor = User::query()->where('email', 'sam@example.com')->sole();

    $checkIn = app(CheckInMember::class)->handle($visitor, $this->club, $occurrence);

    expect($checkIn->lesson_occurrence_id)->toBe($occurrence->id);
    expect(fn () => app(CheckInMember::class)->handle($visitor, $this->club, trialOccurrence($this->club)))
        ->toThrow(__('An active membership at this club is required.'));
});
