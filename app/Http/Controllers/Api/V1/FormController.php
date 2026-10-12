<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Forms\StoreFormSubmission;
use App\Enums\FormAudience;
use App\Enums\FormFieldType;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FormResource;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\Forms\FormSchema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FormController extends Controller
{
    /**
     * Published forms of the member's clubs, either open to all members or
     * assigned to them, with the state of their own response.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        /** @var Collection<int, Form> $forms */
        $forms = Form::query()
            ->published()
            ->with('club')
            ->whereIn('club_id', fn ($query) => $query
                ->select('club_id')
                ->from('memberships')
                ->where('user_id', $user->id)
                ->where('status', MembershipStatus::Active->value))
            ->where(fn ($query) => $query
                ->where('audience', FormAudience::AllMembers->value)
                ->orWhereHas('assignments', fn ($assignment) => $assignment->where('user_id', $user->id)))
            ->orderBy('name')
            ->get();

        $submissions = FormSubmission::query()
            ->where('user_id', $user->id)
            ->where('is_current', true)
            ->whereIn('form_id', $forms->modelKeys())
            ->get()
            ->keyBy('form_id');

        $forms->each(fn (Form $form) => $form->setAttribute(
            'member_state',
            $this->memberState($form, $submissions->get($form->id)),
        ));

        return FormResource::collection($forms);
    }

    /**
     * One form with the fields the member must be shown: their own response's
     * version once they sent it in, the published version while it is open.
     */
    public function show(Request $request, int $formId, StoreFormSubmission $storeFormSubmission): FormResource
    {
        $user = $request->user();

        $form = Form::query()->published()->with('club')->findOrFail($formId);

        abort_unless($storeFormSubmission->isAvailableTo($form, $user), 404);

        $submission = FormSubmission::query()
            ->where('form_id', $form->id)
            ->where('user_id', $user->id)
            ->where('is_current', true)
            ->with('version')
            ->first();

        $locked = (bool) $submission?->locks($form);

        // A sent in response is always rendered with the version it was filled
        // in with, never with a version published since.
        $schema = $locked
            ? $submission->version->schemaObject()
            : $form->publishedVersion->schemaObject();

        $previous = $submission->answers ?? [];

        $form->setAttribute('member_state', $this->memberState($form, $submission));
        $form->setAttribute('member_fields', $schema->fields);
        $form->setAttribute('member_answers', $locked
            ? $this->presentableAnswers($schema, $previous)
            : $schema->prefill($previous));

        return new FormResource($form);
    }

    /**
     * @return array{locked: bool, submitted_at: string|null}
     */
    protected function memberState(Form $form, ?FormSubmission $submission): array
    {
        return [
            'locked' => (bool) $submission?->locks($form),
            'submitted_at' => $submission?->submitted_at->toIso8601String(),
        ];
    }

    /**
     * Signatures are stored as a path on a private disk. The app only needs
     * to know one was given, so never hand out the path itself.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    protected function presentableAnswers(FormSchema $schema, array $answers): array
    {
        foreach ($schema->answerableFields() as $field) {
            if ($field->type === FormFieldType::Signature) {
                $answers[$field->key] = filled($answers[$field->key] ?? null);
            }
        }

        return $answers;
    }
}
