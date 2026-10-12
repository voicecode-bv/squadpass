<?php

use App\Enums\EnrollmentType;
use App\Models\Club;
use App\Models\EnrollmentAbsence;
use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);

    $this->lesson = Lesson::factory()->for($this->club)->create(['name' => 'Padel clinic', 'capacity' => 5]);
    $this->occurrence = LessonOccurrence::factory()->for($this->lesson)->create([
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
    ]);

    Sanctum::actingAs($this->member);
});

test('the schedule lists occurrences of the member clubs only', function () {
    $otherOccurrence = LessonOccurrence::factory()->create([
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
    ]);
    $otherOccurrence->lesson->update(['name' => 'Andere club les']);

    $this->getJson('/api/v1/schedule')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.lesson_name', 'Padel clinic')
        ->assertJsonPath('data.0.enrollment.enrolled_single', false);
});

test('a member can enroll one-time via the api', function () {
    $this->postJson('/api/v1/enrollments/single', ['occurrence_id' => $this->occurrence->id])
        ->assertCreated()
        ->assertJsonPath('data.type', EnrollmentType::Single->value);

    expect(LessonEnrollment::query()->where('user_id', $this->member->id)->count())->toBe(1)
        ->and($this->occurrence->refresh()->booked_count)->toBe(1);
});

test('enrolling in a full occurrence fails with 422', function () {
    $this->occurrence->update(['capacity_override' => 1, 'booked_count' => 1]);

    $this->postJson('/api/v1/enrollments/single', ['occurrence_id' => $this->occurrence->id])
        ->assertStatus(422);

    expect(LessonEnrollment::query()->count())->toBe(0);
});

test('a member can enroll recurring via the api', function () {
    $this->postJson('/api/v1/enrollments/fixed', ['lesson_id' => $this->lesson->id])
        ->assertCreated()
        ->assertJsonPath('data.type', EnrollmentType::Fixed->value);

    expect(LessonEnrollment::query()->where('user_id', $this->member->id)->where('type', EnrollmentType::Fixed)->exists())->toBeTrue();
});

test('a member can cancel their own enrollment but not someone elses', function () {
    $this->postJson('/api/v1/enrollments/single', ['occurrence_id' => $this->occurrence->id]);
    $own = LessonEnrollment::query()->where('user_id', $this->member->id)->firstOrFail();

    $other = LessonEnrollment::factory()->create([
        'club_id' => $this->club->id,
        'lesson_id' => $this->lesson->id,
    ]);

    $this->deleteJson('/api/v1/enrollments/'.$other->id)->assertNotFound();
    $this->deleteJson('/api/v1/enrollments/'.$own->id)->assertOk();

    expect(LessonEnrollment::query()->whereKey($own->id)->exists())->toBeFalse()
        ->and($this->occurrence->refresh()->booked_count)->toBe(0);
});

test('a member can mark an absence for their fixed enrollment', function () {
    $this->postJson('/api/v1/enrollments/fixed', ['lesson_id' => $this->lesson->id]);

    $this->postJson('/api/v1/absences', ['occurrence_id' => $this->occurrence->id])
        ->assertOk();

    expect(EnrollmentAbsence::query()->where('lesson_occurrence_id', $this->occurrence->id)->exists())->toBeTrue();
});

test('the schedule carries the enrollment id, so cancelling needs no second request', function () {
    $this->postJson('/api/v1/enrollments/single', ['occurrence_id' => $this->occurrence->id]);
    $enrollment = LessonEnrollment::query()->where('user_id', $this->member->id)->firstOrFail();

    $this->getJson('/api/v1/schedule')
        ->assertOk()
        ->assertJsonPath('data.0.enrollment.enrolled_single', true)
        ->assertJsonPath('data.0.enrollment.enrollment_id', $enrollment->id);
});

test('an occurrence the member has nothing to do with carries no enrollment id', function () {
    $this->getJson('/api/v1/schedule')
        ->assertOk()
        ->assertJsonPath('data.0.enrollment.enrollment_id', null);
});
