<?php

namespace Database\Factories;

use App\Enums\LessonOccurrenceStatus;
use App\Models\Lesson;
use App\Models\LessonOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonOccurrence>
 */
class LessonOccurrenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+4 weeks');

        return [
            'lesson_id' => Lesson::factory(),
            'club_id' => fn (array $attributes) => Lesson::query()->whereKey($attributes['lesson_id'])->firstOrFail()->club_id,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+60 minutes'),
            'status' => LessonOccurrenceStatus::Scheduled,
            'booked_count' => 0,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(['status' => LessonOccurrenceStatus::Cancelled]);
    }
}
