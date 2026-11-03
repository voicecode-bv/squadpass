<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\NewsPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsPost>
 */
class NewsPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'author_id' => null,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraphs(2, true),
            'pinned' => false,
            'published_at' => now()->subHour(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(['published_at' => now()->addDay()]);
    }

    public function pinned(): static
    {
        return $this->state(['pinned' => true]);
    }
}
