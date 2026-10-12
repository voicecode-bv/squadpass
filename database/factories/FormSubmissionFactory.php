<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmission>
 */
class FormSubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $form = Form::factory()->published();

        return [
            'form_id' => $form,
            'club_id' => fn (array $attributes) => Form::query()->whereKey($attributes['form_id'])->value('club_id'),
            'form_version_id' => fn (array $attributes) => Form::query()->whereKey($attributes['form_id'])->value('published_version_id')
                ?? FormVersion::factory()->create(['form_id' => $attributes['form_id']])->id,
            'user_id' => User::factory(),
            'answers' => [],
            'is_current' => true,
            'submitted_at' => now(),
        ];
    }
}
