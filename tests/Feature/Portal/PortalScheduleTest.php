<?php

use App\Livewire\Portal\MyEnrollments;
use App\Livewire\Portal\Schedule;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);

    $this->lesson = Lesson::factory()->for($this->club)->create(['capacity' => 5]);
    $this->occurrence = LessonOccurrence::factory()->for($this->lesson)->create([
        'club_id' => $this->club->id,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
    ]);
});

test('a member sees upcoming occurrences of their clubs only', function () {
    $this->lesson->update(['name' => 'Eigen club les']);

    $otherClubOccurrence = LessonOccurrence::factory()->create([
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
    ]);
    $otherClubOccurrence->lesson->update(['name' => 'Andere club les']);

    Livewire::actingAs($this->member)
        ->test(Schedule::class)
        ->assertSee('Eigen club les')
        ->assertDontSee('Andere club les');
});

test('a member can enroll one-time via the portal', function () {
    Livewire::actingAs($this->member)
        ->test(Schedule::class)
        ->call('enrollSingle', $this->occurrence->id);

    expect(LessonEnrollment::query()->where('user_id', $this->member->id)->count())->toBe(1)
        ->and($this->occurrence->refresh()->booked_count)->toBe(1);
});

test('a member can enroll recurring via the portal', function () {
    Livewire::actingAs($this->member)
        ->test(Schedule::class)
        ->call('enrollFixed', $this->occurrence->id);

    expect(LessonEnrollment::query()->where('user_id', $this->member->id)->where('lesson_id', $this->lesson->id)->exists())->toBeTrue()
        ->and($this->occurrence->refresh()->booked_count)->toBe(1);
});

test('a non-member enrolling gets no enrollment', function () {
    $stranger = User::factory()->create();

    Livewire::actingAs($stranger)
        ->test(Schedule::class)
        ->call('enrollSingle', $this->occurrence->id);

    expect(LessonEnrollment::query()->count())->toBe(0)
        ->and($this->occurrence->refresh()->booked_count)->toBe(0);
});

test('a member can cancel an enrollment from my enrollments', function () {
    Livewire::actingAs($this->member)
        ->test(Schedule::class)
        ->call('enrollSingle', $this->occurrence->id);

    $enrollment = LessonEnrollment::query()->where('user_id', $this->member->id)->firstOrFail();

    Livewire::actingAs($this->member)
        ->test(MyEnrollments::class)
        ->call('cancel', $enrollment->id);

    expect(LessonEnrollment::query()->count())->toBe(0)
        ->and($this->occurrence->refresh()->booked_count)->toBe(0);
});

test('a member can cancel a recurring enrollment from the schedule', function () {
    Livewire::actingAs($this->member)
        ->test(Schedule::class)
        ->call('enrollFixed', $this->occurrence->id)
        ->call('cancelFixed', $this->occurrence->id);

    expect(LessonEnrollment::query()->count())->toBe(0)
        ->and($this->occurrence->refresh()->booked_count)->toBe(0);
});

test('cancelling a recurring enrollment requires an own fixed enrollment', function () {
    $other = User::factory()->create();
    $this->club->addMember($other);

    Livewire::actingAs($other)
        ->test(Schedule::class)
        ->call('enrollFixed', $this->occurrence->id);

    expect(fn () => Livewire::actingAs($this->member)
        ->test(Schedule::class)
        ->call('cancelFixed', $this->occurrence->id)
    )->toThrow(ModelNotFoundException::class);

    expect(LessonEnrollment::query()->count())->toBe(1);
});

test('a member cannot cancel someone elses enrollment', function () {
    $other = User::factory()->create();
    $this->club->addMember($other);

    Livewire::actingAs($other)
        ->test(Schedule::class)
        ->call('enrollSingle', $this->occurrence->id);

    $enrollment = LessonEnrollment::query()->where('user_id', $other->id)->firstOrFail();

    expect(fn () => Livewire::actingAs($this->member)
        ->test(MyEnrollments::class)
        ->call('cancel', $enrollment->id)
    )->toThrow(ModelNotFoundException::class);

    expect(LessonEnrollment::query()->count())->toBe(1);
});
