<?php

namespace Database\Factories;

use App\Enums\FormFieldType;
use App\Models\Form;
use App\Models\FormVersion;
use App\Services\Forms\FormField;
use App\Services\Forms\FormSchema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormVersion>
 */
class FormVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'version' => 1,
            'schema' => (new FormSchema([
                new FormField(
                    key: FormField::newKey(),
                    type: FormFieldType::Text,
                    label: 'Naam',
                    required: true,
                ),
            ]))->toArray(),
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['published_at' => null]);
    }
}
