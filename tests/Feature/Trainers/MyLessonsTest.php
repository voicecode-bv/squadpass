<?php

use App\Actions\Lessons\EnrollSingle;
use App\Livewire\Trainers\MyLessons;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\LessonOccurrence;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->trainerUser = User::factory()->create();
    $this->trainer = Trainer::factory()->for($this->club)->create(['user_id' => $this->trainerUser->id]);

    $this->lesson = Lesson::factory()->for($this->club)->create(['name' => 'Eigen les']);
    $this->lesson->trainers()->attach($this->trainer);

    $this->occurrence = LessonOccurrence::factory()->for($this->lesson)->create([
        'club_id' => $this->club->id,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
    ]);
});

test('a trainer sees only occurrences of their own lessons', function () {
    $otherLesson = Lesson::factory()->for($this->club)->create(['name' => 'Andermans les']);
    LessonOccurrence::factory()->for($otherLesson)->create([
        'club_id' => $this->club->id,
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHour(),
    ]);

    Livewire::actingAs($this->trainerUser)
        ->test(MyLessons::class)
        ->assertSee('Eigen les')
        ->assertSee($this->club->name)
        ->assertDontSee('Andermans les');
});

test('a trainer sees lessons of all clubs they train at', function () {
    $otherClub = Club::factory()->create();
    $otherTrainer = Trainer::factory()->for($otherClub)->create(['user_id' => $this->trainerUser->id]);
    $otherLesson = Lesson::factory()->for($otherClub)->create(['name' => 'Les elders']);
    $otherLesson->trainers()->attach($otherTrainer);
    LessonOccurrence::factory()->for($otherLesson)->create([
        'club_id' => $otherClub->id,
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHour(),
    ]);

    Livewire::actingAs($this->trainerUser)
        ->test(MyLessons::class)
        ->assertSee('Eigen les')
        ->assertSee('Les elders');
});

test('a trainer can view the participants of their occurrence', function () {
    $member = User::factory()->create(['name' => 'Piet Deelnemer']);
    $this->club->addMember($member);
    app(EnrollSingle::class)->handle($member, $this->occurrence);

    Livewire::actingAs($this->trainerUser)
        ->test(MyLessons::class)
        ->call('selectOccurrence', $this->occurrence->id)
        ->assertSee('Piet Deelnemer');
});

test('a trainer cannot select occurrences of lessons they do not teach', function () {
    $foreignOccurrence = LessonOccurrence::factory()->create([
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
    ]);

    expect(fn () => Livewire::actingAs($this->trainerUser)
        ->test(MyLessons::class)
        ->call('selectOccurrence', $foreignOccurrence->id)
    )->toThrow(ModelNotFoundException::class);
});
