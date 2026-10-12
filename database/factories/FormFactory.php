<?php

namespace Database\Factories;

use App\Enums\FormAudience;
use App\Enums\FormFieldType;
use App\Enums\FormStatus;
use App\Models\Club;
use App\Models\Form;
use App\Models\FormVersion;
use App\Services\Forms\FormField;
use App\Services\Forms\FormSchema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Form>
 */
class FormFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'name' => fake()->sentence(2),
            'description' => fake()->sentence(),
            'status' => FormStatus::Draft,
            'audience' => FormAudience::AllMembers,
            'published_version_id' => null,
        ];
    }

    /**
     * A published form with a single required text field, unless a schema is
     * given.
     */
    public function published(?FormSchema $schema = null): static
    {
        $schema ??= new FormSchema([
            new FormField(
                key: FormField::newKey(),
                type: FormFieldType::Text,
                label: 'Naam',
                required: true,
            ),
        ]);

        return $this->state(['status' => FormStatus::Published])
            ->afterCreating(function (Form $form) use ($schema): void {
                $version = FormVersion::create([
                    'form_id' => $form->id,
                    'version' => 1,
                    'schema' => $schema->toArray(),
                    'published_at' => now(),
                ]);

                $form->update(['published_version_id' => $version->id]);
            });
    }

    public function assignedOnly(): static
    {
        return $this->state(['audience' => FormAudience::Assigned]);
    }
}
