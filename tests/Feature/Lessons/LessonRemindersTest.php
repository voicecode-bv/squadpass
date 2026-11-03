<?php

use App\Enums\EnrollmentType;
use App\Enums\LessonOccurrenceStatus;
use App\Enums\StaffRole;
use App\Livewire\Clubs\Settings;
use App\Livewire\Settings\Profile;
use App\Models\Club;
use App\Models\EnrollmentAbsence;
use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\User;
use App\Notifications\LessonReminder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function reminderOccurrence(Club $club, int $hoursAhead, array $attributes = []): LessonOccurrence
{
    $lesson = Lesson::factory()->for($club)->create(['capacity' => 10]);

    return LessonOccurrence::factory()->for($lesson)->create([
        'club_id' => $club->id,
        'starts_at' => now()->addHours($hoursAhead),
        'ends_at' => now()->addHours($hoursAhead + 1),
        ...$attributes,
    ]);
}

function bookSingle(LessonOccurrence $occurrence, User $member): LessonEnrollment
{
    return LessonEnrollment::factory()->create([
        'club_id' => $occurrence->club_id,
        'user_id' => $member->id,
        'type' => EnrollmentType::Single,
        'lesson_id' => null,
        'lesson_occurrence_id' => $occurrence->id,
    ]);
}

beforeEach(function () {
    $this->freezeTime();

    $this->club = Club::factory()->create(['lesson_reminder_hours' => 24]);
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);
});

test('members booked for a lesson within the reminder window get one reminder', function () {
    Notification::fake();
    $occurrence = reminderOccurrence($this->club, 20);
    bookSingle($occurrence, $this->member);

    $this->artisan('lessons:send-reminders')->assertSuccessful();
    $this->artisan('lessons:send-reminders')->assertSuccessful();

    Notification::assertSentToTimes($this->member, LessonReminder::class, 1);
    expect($occurrence->refresh()->reminders_sent_at)->not->toBeNull();
});

test('fixed enrollments get a reminder unless the member reported absent', function () {
    Notification::fake();
    $occurrence = reminderOccurrence($this->club, 20);
    $absentMember = User::factory()->create();
    $this->club->addMember($absentMember);

    LessonEnrollment::factory()->create(['club_id' => $this->club->id, 'user_id' => $this->member->id, 'lesson_id' => $occurrence->lesson_id]);
    $absentEnrollment = LessonEnrollment::factory()->create(['club_id' => $this->club->id, 'user_id' => $absentMember->id, 'lesson_id' => $occurrence->lesson_id]);
    EnrollmentAbsence::query()->create(['lesson_enrollment_id' => $absentEnrollment->id, 'lesson_occurrence_id' => $occurrence->id]);

    $this->artisan('lessons:send-reminders')->assertSuccessful();

    Notification::assertSentTo($this->member, LessonReminder::class);
    Notification::assertNotSentTo($absentMember, LessonReminder::class);
});

test('no reminder goes out for lessons outside the window, cancelled lessons or clubs without reminders', function () {
    Notification::fake();
    bookSingle(reminderOccurrence($this->club, 30), $this->member);
    bookSingle(reminderOccurrence($this->club, 20, ['status' => LessonOccurrenceStatus::Cancelled]), $this->member);

    $quietClub = Club::factory()->create(['lesson_reminder_hours' => null]);
    $quietClub->addMember($this->member);
    bookSingle(reminderOccurrence($quietClub, 2), $this->member);

    $this->artisan('lessons:send-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

test('members who turned reminders off get none', function () {
    Notification::fake();
    $this->member->update(['lesson_reminders' => false]);
    bookSingle(reminderOccurrence($this->club, 20), $this->member);

    $this->artisan('lessons:send-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

test('members switch lesson reminders off in their profile', function () {
    Livewire::actingAs($this->member)
        ->test(Profile::class)
        ->set('lesson_reminders', false);

    expect($this->member->refresh()->lesson_reminders)->toBeFalse();
});

test('staff turn lesson reminders on with the number of hours before the lesson', function () {
    $owner = User::factory()->create();
    $club = Club::factory()->create(['lesson_reminder_hours' => null]);
    $club->addStaff($owner, StaffRole::Owner);

    Livewire::actingAs($owner)
        ->test(Settings::class, ['club' => $club])
        ->set('lesson_reminders', true)
        ->set('lesson_reminder_hours', 12)
        ->call('save')
        ->assertHasNoErrors();

    expect($club->refresh()->lesson_reminder_hours)->toBe(12);

    Livewire::actingAs($owner)
        ->test(Settings::class, ['club' => $club])
        ->set('lesson_reminders', false)
        ->call('save');

    expect($club->refresh()->lesson_reminder_hours)->toBeNull();
});
