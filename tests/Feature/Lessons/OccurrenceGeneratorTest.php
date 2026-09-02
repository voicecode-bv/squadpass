<?php

use App\Enums\LessonOccurrenceStatus;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\LessonOccurrence;
use App\Services\Lessons\OccurrenceGenerator;
use Illuminate\Support\Carbon;

function makeWeeklyLesson(array $attributes = []): Lesson
{
    return Lesson::factory()
        ->for(Club::factory()->create(['timezone' => 'Europe/Amsterdam']))
        ->create(array_merge([
            'day_of_week' => 1, // Monday
            'start_time' => '19:00:00',
            'duration_minutes' => 60,
            'starts_on' => now()->toDateString(),
            'ends_on' => null,
        ], $attributes));
}

test('it generates weekly occurrences for the rolling window', function () {
    $lesson = makeWeeklyLesson();

    $generated = app(OccurrenceGenerator::class)->generateForLesson($lesson);

    expect($generated)->toBeGreaterThanOrEqual(8)
        ->and($lesson->occurrences()->count())->toBe($generated);

    $first = $lesson->occurrences()->orderBy('starts_at')->first();

    expect($first->starts_at->timezone('Europe/Amsterdam')->isoWeekday())->toBe(1)
        ->and($first->starts_at->timezone('Europe/Amsterdam')->format('H:i'))->toBe('19:00')
        ->and($first->ends_at->diffInMinutes($first->starts_at))->toEqual(-60.0);
});

test('generation is idempotent', function () {
    $lesson = makeWeeklyLesson();
    $generator = app(OccurrenceGenerator::class);

    $first = $generator->generateForLesson($lesson);
    $second = $generator->generateForLesson($lesson);

    expect($second)->toBe(0)
        ->and($lesson->occurrences()->count())->toBe($first);
});

test('it respects the lesson end date', function () {
    $lesson = makeWeeklyLesson([
        'ends_on' => now()->addWeeks(2)->toDateString(),
    ]);

    app(OccurrenceGenerator::class)->generateForLesson($lesson);

    expect($lesson->occurrences()->count())->toBeLessThanOrEqual(3)
        ->and($lesson->occurrences()->max('starts_at'))->toBeLessThanOrEqual(now()->addWeeks(2)->endOfDay());
});

test('inactive lessons generate nothing', function () {
    $lesson = makeWeeklyLesson(['active' => false]);

    expect(app(OccurrenceGenerator::class)->generateForLesson($lesson))->toBe(0)
        ->and($lesson->occurrences()->count())->toBe(0);
});

test('local start time is preserved across a DST transition', function () {
    // EU DST ends 2026-10-25: Amsterdam goes from UTC+2 to UTC+1.
    Carbon::setTestNow('2026-10-12 08:00:00');

    $lesson = makeWeeklyLesson(['starts_on' => '2026-10-12']);

    app(OccurrenceGenerator::class)->generateForLesson($lesson);

    $occurrences = $lesson->occurrences()->orderBy('starts_at')->get();
    $beforeDst = $occurrences->first(fn ($occurrence) => $occurrence->starts_at->timezone('Europe/Amsterdam')->toDateString() === '2026-10-19');
    $afterDst = $occurrences->first(fn ($occurrence) => $occurrence->starts_at->timezone('Europe/Amsterdam')->toDateString() === '2026-10-26');

    expect($beforeDst->starts_at->timezone('Europe/Amsterdam')->format('H:i'))->toBe('19:00')
        ->and($afterDst->starts_at->timezone('Europe/Amsterdam')->format('H:i'))->toBe('19:00')
        ->and($beforeDst->starts_at->format('H:i'))->toBe('17:00')
        ->and($afterDst->starts_at->format('H:i'))->toBe('18:00');

    Carbon::setTestNow();
});

test('a cancelled occurrence stays cancelled after regeneration', function () {
    $lesson = makeWeeklyLesson();
    $generator = app(OccurrenceGenerator::class);
    $generator->generateForLesson($lesson);

    $occurrence = $lesson->occurrences()->orderBy('starts_at')->firstOrFail();
    $occurrence->update(['status' => LessonOccurrenceStatus::Cancelled]);

    $generator->generateForLesson($lesson);

    expect($occurrence->refresh()->status)->toBe(LessonOccurrenceStatus::Cancelled);
});

test('resync removes unbooked future occurrences but keeps booked ones', function () {
    $lesson = makeWeeklyLesson();
    $generator = app(OccurrenceGenerator::class);
    $generator->generateForLesson($lesson);

    $booked = $lesson->occurrences()->orderBy('starts_at')->firstOrFail();
    $booked->update(['booked_count' => 3]);

    $lesson->update(['start_time' => '20:00:00']);
    $generator->resyncFutureOccurrences($lesson->refresh());

    expect(LessonOccurrence::query()->whereKey($booked->id)->exists())->toBeTrue();

    $newTimes = $lesson->occurrences()
        ->whereKeyNot($booked->id)
        ->get()
        ->map(fn ($occurrence) => $occurrence->starts_at->timezone('Europe/Amsterdam')->format('H:i'))
        ->unique();

    expect($newTimes->all())->toBe(['20:00']);
});

test('the scheduled command generates occurrences for all active lessons', function () {
    makeWeeklyLesson();
    makeWeeklyLesson(['active' => false]);

    $this->artisan('lessons:generate-occurrences')->assertSuccessful();

    expect(LessonOccurrence::query()->count())->toBeGreaterThan(0)
        ->and(LessonOccurrence::query()->distinct('lesson_id')->count('lesson_id'))->toBe(1);
});
