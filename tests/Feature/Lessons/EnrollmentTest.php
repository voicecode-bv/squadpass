<?php

use App\Actions\Lessons\CancelEnrollment;
use App\Actions\Lessons\EnrollFixed;
use App\Actions\Lessons\EnrollSingle;
use App\Actions\Lessons\MarkAbsence;
use App\Enums\EnrollmentType;
use App\Enums\LessonOccurrenceStatus;
use App\Enums\MembershipStatus;
use App\Exceptions\EnrollmentException;
use App\Models\Club;
use App\Models\EnrollmentAbsence;
use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\User;
use App\Services\Lessons\OccurrenceGenerator;

function makeClubMember(Club $club): User
{
    $user = User::factory()->create();
    $club->addMember($user);

    return $user;
}

function makeOccurrence(Lesson $lesson, int $daysAhead = 3): LessonOccurrence
{
    return LessonOccurrence::factory()->for($lesson)->create([
        'club_id' => $lesson->club_id,
        'starts_at' => now()->addDays($daysAhead)->setTime(19, 0),
        'ends_at' => now()->addDays($daysAhead)->setTime(20, 0),
    ]);
}

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->lesson = Lesson::factory()->for($this->club)->create(['capacity' => 2]);
});

test('a member can enroll once for an occurrence', function () {
    $member = makeClubMember($this->club);
    $occurrence = makeOccurrence($this->lesson);

    $enrollment = app(EnrollSingle::class)->handle($member, $occurrence);

    expect($enrollment->type)->toBe(EnrollmentType::Single)
        ->and($occurrence->refresh()->booked_count)->toBe(1);

    expect(fn () => app(EnrollSingle::class)->handle($member, $occurrence))
        ->toThrow(EnrollmentException::class);
});

test('enrollment requires an active membership', function () {
    $stranger = User::factory()->create();
    $occurrence = makeOccurrence($this->lesson);

    expect(fn () => app(EnrollSingle::class)->handle($stranger, $occurrence))
        ->toThrow(EnrollmentException::class);
});

test('a paused membership cannot enroll', function () {
    $member = makeClubMember($this->club);
    $this->club->memberships()->where('user_id', $member->id)->first()
        ->update(['status' => MembershipStatus::Paused]);

    $occurrence = makeOccurrence($this->lesson);

    expect(fn () => app(EnrollSingle::class)->handle($member, $occurrence))
        ->toThrow(EnrollmentException::class);
});

test('the last spot can only be claimed once', function () {
    $occurrence = makeOccurrence($this->lesson);
    $occurrence->update(['capacity_override' => 1]);

    $first = makeClubMember($this->club);
    $second = makeClubMember($this->club);

    app(EnrollSingle::class)->handle($first, $occurrence->refresh());

    expect(fn () => app(EnrollSingle::class)->handle($second, $occurrence->refresh()))
        ->toThrow(EnrollmentException::class, __('The lesson on :date is full.', [
            'date' => $occurrence->starts_at->timezone($this->club->timezone)->isoFormat('D MMMM YYYY HH:mm'),
        ]));

    expect($occurrence->refresh()->booked_count)->toBe(1);
});

test('cancelled or past occurrences cannot be booked', function () {
    $member = makeClubMember($this->club);

    $cancelled = makeOccurrence($this->lesson);
    $cancelled->update(['status' => LessonOccurrenceStatus::Cancelled]);

    $past = LessonOccurrence::factory()->for($this->lesson)->create([
        'club_id' => $this->club->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
    ]);

    expect(fn () => app(EnrollSingle::class)->handle($member, $cancelled))->toThrow(EnrollmentException::class)
        ->and(fn () => app(EnrollSingle::class)->handle($member, $past))->toThrow(EnrollmentException::class);
});

test('fixed enrollment claims a spot on every future occurrence', function () {
    $member = makeClubMember($this->club);
    $first = makeOccurrence($this->lesson, 3);
    $second = makeOccurrence($this->lesson, 10);

    $enrollment = app(EnrollFixed::class)->handle($member, $this->lesson);

    expect($enrollment->type)->toBe(EnrollmentType::Fixed)
        ->and($first->refresh()->booked_count)->toBe(1)
        ->and($second->refresh()->booked_count)->toBe(1);
});

test('fixed enrollment fails and rolls back when one occurrence is full', function () {
    $member = makeClubMember($this->club);
    $first = makeOccurrence($this->lesson, 3);
    $full = makeOccurrence($this->lesson, 10);
    $full->update(['capacity_override' => 1, 'booked_count' => 1]);

    expect(fn () => app(EnrollFixed::class)->handle($member, $this->lesson))
        ->toThrow(EnrollmentException::class);

    expect($first->refresh()->booked_count)->toBe(0)
        ->and($this->lesson->enrollments()->count())->toBe(0);
});

test('an existing single enrollment transfers into a fixed enrollment', function () {
    $member = makeClubMember($this->club);
    $first = makeOccurrence($this->lesson, 3);
    $second = makeOccurrence($this->lesson, 10);

    app(EnrollSingle::class)->handle($member, $first);
    app(EnrollFixed::class)->handle($member, $this->lesson);

    expect($first->refresh()->booked_count)->toBe(1)
        ->and($second->refresh()->booked_count)->toBe(1)
        ->and($this->lesson->enrollments()->where('user_id', $member->id)->count())->toBe(1);
});

test('an absence frees the spot and rejoining reclaims it', function () {
    $member = makeClubMember($this->club);
    $occurrence = makeOccurrence($this->lesson);

    $enrollment = app(EnrollFixed::class)->handle($member, $this->lesson);

    app(MarkAbsence::class)->handle($enrollment, $occurrence->refresh());

    expect($occurrence->refresh()->booked_count)->toBe(0);

    // Someone else can take the freed spot.
    $other = makeClubMember($this->club);
    app(EnrollSingle::class)->handle($other, $occurrence->refresh());

    expect($occurrence->refresh()->booked_count)->toBe(1);

    // Rejoining reclaims a spot for the fixed member.
    app(EnrollSingle::class)->handle($member, $occurrence->refresh());

    expect($occurrence->refresh()->booked_count)->toBe(2)
        ->and($enrollment->absences()->count())->toBe(0);
});

test('cancelling a single enrollment releases the spot', function () {
    $member = makeClubMember($this->club);
    $occurrence = makeOccurrence($this->lesson);

    $enrollment = app(EnrollSingle::class)->handle($member, $occurrence);

    app(CancelEnrollment::class)->handle($enrollment);

    expect($occurrence->refresh()->booked_count)->toBe(0)
        ->and($this->lesson->club->id)->toBe($this->club->id);
});

test('cancelling a fixed enrollment releases future spots but not absences', function () {
    $member = makeClubMember($this->club);
    $attending = makeOccurrence($this->lesson, 3);
    $absent = makeOccurrence($this->lesson, 10);

    $enrollment = app(EnrollFixed::class)->handle($member, $this->lesson);
    app(MarkAbsence::class)->handle($enrollment, $absent->refresh());

    app(CancelEnrollment::class)->handle($enrollment->refresh());

    expect($attending->refresh()->booked_count)->toBe(0)
        ->and($absent->refresh()->booked_count)->toBe(0)
        ->and(EnrollmentAbsence::query()->count())->toBe(0)
        ->and(LessonEnrollment::query()->count())->toBe(0);
});

test('newly generated occurrences count fixed enrollments', function () {
    $member = makeClubMember($this->club);
    $this->lesson->update([
        'day_of_week' => now($this->club->timezone)->addDays(2)->isoWeekday(),
        'start_time' => '19:00:00',
        'starts_on' => now()->toDateString(),
    ]);

    app(EnrollFixed::class)->handle($member, $this->lesson);

    app(OccurrenceGenerator::class)->generateForLesson($this->lesson->refresh());

    $counts = $this->lesson->occurrences()->pluck('booked_count')->unique();

    expect($counts->all())->toBe([1]);
});
