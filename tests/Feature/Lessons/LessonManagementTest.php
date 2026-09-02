<?php

use App\Enums\LessonOccurrenceStatus;
use App\Enums\StaffRole;
use App\Livewire\Lessons\Form;
use App\Livewire\Lessons\Schedule;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\Trainer;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->staff = User::factory()->create();
    $this->club->addStaff($this->staff, StaffRole::Admin);
});

test('staff can create a lesson with trainers and occurrences are generated', function () {
    $trainer = Trainer::factory()->for($this->club)->create();

    Livewire::actingAs($this->staff)
        ->test(Form::class, ['club' => $this->club])
        ->set('name', 'Squash beginners')
        ->set('capacity', 8)
        ->set('schedules', [
            ['day_of_week' => 3, 'start_time' => '19:30', 'duration_minutes' => 60],
        ])
        ->set('trainer_ids', [$trainer->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $lesson = Lesson::query()->where('name', 'Squash beginners')->firstOrFail();

    expect($lesson->trainers()->pluck('trainers.id')->all())->toBe([$trainer->id])
        ->and($lesson->occurrences()->count())->toBeGreaterThan(0);
});

test('multiple weekly time slots create one lesson per slot', function () {
    $trainer = Trainer::factory()->for($this->club)->create();

    Livewire::actingAs($this->staff)
        ->test(Form::class, ['club' => $this->club])
        ->set('name', 'Padel training')
        ->set('capacity', 8)
        ->set('schedules', [
            ['day_of_week' => 1, 'start_time' => '19:00', 'duration_minutes' => 60],
            ['day_of_week' => 4, 'start_time' => '20:30', 'duration_minutes' => 90],
        ])
        ->set('trainer_ids', [$trainer->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $lessons = Lesson::query()->where('name', 'Padel training')->orderBy('day_of_week')->get();

    expect($lessons)->toHaveCount(2)
        ->and($lessons[0]->day_of_week)->toBe(1)
        ->and(substr($lessons[0]->start_time, 0, 5))->toBe('19:00')
        ->and($lessons[1]->day_of_week)->toBe(4)
        ->and($lessons[1]->duration_minutes)->toBe(90);

    $lessons->each(function (Lesson $lesson) use ($trainer) {
        expect($lesson->occurrences()->count())->toBeGreaterThan(0)
            ->and($lesson->trainers()->pluck('trainers.id')->all())->toBe([$trainer->id]);

        $weekdays = $lesson->occurrences()->get()
            ->map(fn ($occurrence) => $occurrence->starts_at->timezone($lesson->club->timezone)->isoWeekday())
            ->unique();

        expect($weekdays->all())->toBe([$lesson->day_of_week]);
    });
});

test('an empty schedule list is rejected', function () {
    Livewire::actingAs($this->staff)
        ->test(Form::class, ['club' => $this->club])
        ->set('name', 'Padel training')
        ->set('capacity', 8)
        ->set('schedules', [])
        ->call('save')
        ->assertHasErrors(['schedules']);

    expect(Lesson::query()->count())->toBe(0);
});

test('editing a lesson keeps a single time slot and resyncs occurrences', function () {
    $lesson = Lesson::factory()->for($this->club)->create([
        'day_of_week' => 2,
        'start_time' => '18:00:00',
        'duration_minutes' => 60,
    ]);

    Livewire::actingAs($this->staff)
        ->test(Form::class, ['club' => $this->club, 'lesson' => $lesson])
        ->set('schedules', [
            ['day_of_week' => 5, 'start_time' => '20:00', 'duration_minutes' => 45],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $lesson->refresh();

    expect($lesson->day_of_week)->toBe(5)
        ->and(substr($lesson->start_time, 0, 5))->toBe('20:00')
        ->and($lesson->duration_minutes)->toBe(45)
        ->and(Lesson::query()->count())->toBe(1);
});

test('a trainer from another club cannot be assigned', function () {
    $foreignTrainer = Trainer::factory()->create();

    Livewire::actingAs($this->staff)
        ->test(Form::class, ['club' => $this->club])
        ->set('name', 'Padel')
        ->set('capacity', 4)
        ->set('trainer_ids', [$foreignTrainer->id])
        ->call('save')
        ->assertHasNoErrors();

    $lesson = Lesson::query()->where('name', 'Padel')->firstOrFail();

    expect($lesson->trainers()->count())->toBe(0);
});

test('non-staff cannot open the lesson form', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Form::class, ['club' => $this->club])
        ->assertForbidden();
});

test('a lesson of another club returns 404 on the edit form', function () {
    $foreignLesson = Lesson::factory()->create();

    Livewire::actingAs($this->staff)
        ->test(Form::class, ['club' => $this->club, 'lesson' => $foreignLesson])
        ->assertNotFound();
});

// Adding trainers now runs through email invitations; see Feature/Trainers/TrainerInvitationTest.

test('staff can cancel and restore an occurrence', function () {
    $lesson = Lesson::factory()->for($this->club)->create();
    $occurrence = $lesson->occurrences()->create([
        'club_id' => $this->club->id,
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHour(),
        'status' => LessonOccurrenceStatus::Scheduled,
    ]);

    Livewire::actingAs($this->staff)
        ->test(Schedule::class, ['club' => $this->club])
        ->call('cancelOccurrence', $occurrence->id);

    expect($occurrence->refresh()->status)->toBe(LessonOccurrenceStatus::Cancelled);

    Livewire::actingAs($this->staff)
        ->test(Schedule::class, ['club' => $this->club])
        ->call('restoreOccurrence', $occurrence->id);

    expect($occurrence->refresh()->status)->toBe(LessonOccurrenceStatus::Scheduled);
});
