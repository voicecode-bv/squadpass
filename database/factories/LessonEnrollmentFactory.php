<?php

namespace Database\Factories;

use App\Enums\EnrollmentType;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonEnrollment>
 */
class LessonEnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => fn (array $attributes) => isset($attributes['lesson_id'])
                ? Lesson::query()->whereKey($attributes['lesson_id'])->firstOrFail()->club_id
                : Club::factory(),
            'user_id' => User::factory(),
            'type' => EnrollmentType::Fixed,
            'lesson_id' => Lesson::factory(),
            'lesson_occurrence_id' => null,
        ];
    }
}
