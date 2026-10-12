<?php

use App\Enums\EnrollmentType;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\Trainer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->trainerUser = User::factory()->create();
    $this->trainer = Trainer::factory()->for($this->club)->create(['user_id' => $this->trainerUser->id]);

    $this->lesson = Lesson::factory()->for($this->club)->create(['name' => 'Padel clinic', 'capacity' => 8]);
    $this->lesson->trainers()->attach($this->trainer);

    $this->occurrence = LessonOccurrence::factory()->for($this->lesson)->create([
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
    ]);

    Sanctum::actingAs($this->trainerUser);
});

test('a trainer sees only the upcoming occurrences of their own lessons', function () {
    LessonOccurrence::factory()->create([
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
    ]);

    $this->getJson('/api/v1/trainer/lessons')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->occurrence->id)
        ->assertJsonPath('data.0.lesson_name', 'Padel clinic')
        ->assertJsonPath('data.0.capacity', 8);
});

test('the participant list shows members with a one-time or recurring enrollment', function () {
    $single = User::factory()->create(['name' => 'Anna Bakker']);
    $fixed = User::factory()->create(['name' => 'Bram Jansen']);

    LessonEnrollment::factory()->create([
        'club_id' => $this->club->id,
        'user_id' => $single->id,
        'lesson_id' => $this->lesson->id,
        'lesson_occurrence_id' => $this->occurrence->id,
        'type' => EnrollmentType::Single,
    ]);

    LessonEnrollment::factory()->create([
        'club_id' => $this->club->id,
        'user_id' => $fixed->id,
        'lesson_id' => $this->lesson->id,
        'lesson_occurrence_id' => null,
        'type' => EnrollmentType::Fixed,
    ]);

    $this->getJson("/api/v1/trainer/occurrences/{$this->occurrence->id}/participants")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Anna Bakker')
        ->assertJsonPath('data.1.name', 'Bram Jansen');
});

test('a trainer cannot open the participants of someone elses lesson', function () {
    $other = LessonOccurrence::factory()->create([
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
    ]);

    $this->getJson("/api/v1/trainer/occurrences/{$other->id}/participants")->assertNotFound();
});

test('a member who does not train anywhere gets an empty lesson list', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/trainer/lessons')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('me reports whether the user also trains somewhere', function () {
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.is_trainer', true);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.is_trainer', false);
});
