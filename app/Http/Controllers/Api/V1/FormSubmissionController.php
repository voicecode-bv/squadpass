<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Forms\StoreFormSubmission;
use App\Exceptions\FormException;
use App\Http\Controllers\Controller;
use App\Models\Form;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormSubmissionController extends Controller
{
    /**
     * Send in the member's answers for the form's published version.
     */
    public function store(Request $request, int $formId, StoreFormSubmission $storeFormSubmission): JsonResponse
    {
        $user = $request->user();

        $form = Form::query()->published()->findOrFail($formId);

        abort_unless($storeFormSubmission->isAvailableTo($form, $user), 404);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        try {
            $submission = $storeFormSubmission->handle($form, $user, $validated['answers']);
        } catch (FormException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => __('Your answers have been saved.'),
            'submitted_at' => $submission->submitted_at->toIso8601String(),
        ], 201);
    }
}
