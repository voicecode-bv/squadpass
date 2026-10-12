<?php

namespace App\Actions\Forms;

use App\Enums\FormAudience;
use App\Enums\FormFieldType;
use App\Exceptions\FormException;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\Forms\FormField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StoreFormSubmission
{
    /**
     * Store a member's answers against the form's published version. Earlier
     * submissions are kept, only their is_current flag is cleared. A response
     * that still locks the form is refused: sent in is sent in.
     *
     * @param  array<string, mixed>  $answers
     */
    public function handle(Form $form, User $user, array $answers): FormSubmission
    {
        throw_unless($form->isPublished(), FormException::notPublished());
        throw_unless($this->isAvailableTo($form, $user), FormException::notAvailableToUser());

        $version = $form->publishedVersion;
        $schema = $version->schemaObject();

        $validated = Validator::make(
            ['answers' => $answers],
            $schema->validationRules(),
            [],
            $schema->validationAttributes(),
        )->validate();

        return DB::transaction(function () use ($form, $user, $version, $schema, $validated): FormSubmission {
            // Locking the current row keeps two simultaneous submits from both
            // passing the check and writing a second response.
            $current = FormSubmission::query()
                ->where('form_id', $form->id)
                ->where('user_id', $user->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();

            throw_if($current?->locks($form), FormException::alreadySubmitted());

            $storedAnswers = $this->storeSignatures($form, $user, $schema->answerableFields(), $validated['answers'] ?? []);

            $current?->update(['is_current' => false]);

            return FormSubmission::create([
                'club_id' => $form->club_id,
                'form_id' => $form->id,
                'form_version_id' => $version->id,
                'user_id' => $user->id,
                'answers' => $storedAnswers,
                'is_current' => true,
                'submitted_at' => now(),
            ]);
        });
    }

    /**
     * Members of the club may fill in an open form; a form limited to
     * assigned members only when they were assigned.
     */
    public function isAvailableTo(Form $form, User $user): bool
    {
        if (! $user->isMemberOf($form->club)) {
            return false;
        }

        if ($form->audience === FormAudience::AllMembers) {
            return true;
        }

        return $form->assignments()->where('user_id', $user->id)->exists();
    }

    /**
     * Signatures arrive as a PNG data URL. Keep the image on the private disk
     * and store only its path, so the answers column stays small.
     *
     * @param  list<FormField>  $fields
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function storeSignatures(Form $form, User $user, array $fields, array $answers): array
    {
        foreach ($fields as $field) {
            if ($field->type !== FormFieldType::Signature) {
                continue;
            }

            $value = $answers[$field->key] ?? null;

            if (! is_string($value) || ! str_starts_with($value, 'data:image/png;base64,')) {
                continue;
            }

            $binary = base64_decode(Str::after($value, 'data:image/png;base64,'), true);

            if ($binary === false) {
                unset($answers[$field->key]);

                continue;
            }

            $path = sprintf('form-signatures/%d/%d/%s.png', $form->id, $user->id, Str::ulid());
            Storage::disk('local')->put($path, $binary);

            $answers[$field->key] = $path;
        }

        return $answers;
    }
}
