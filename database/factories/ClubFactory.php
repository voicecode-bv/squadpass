<?php

namespace Database\Factories;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'timezone' => 'Europe/Amsterdam',
            'currency' => 'EUR',
            'self_signup' => true,
        ];
    }

    public function withMollieKey(string $key = 'test_fake_api_key'): static
    {
        return $this->state(['mollie_api_key' => $key]);
    }
}
