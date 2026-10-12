<?php

namespace App\Http\Controllers;

use App\Enums\FormFieldType;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormSignatureController extends Controller
{
    /**
     * Serve a signature from the private disk to the member who signed it or
     * to the club's staff.
     */
    public function __invoke(FormSubmission $submission, string $field): StreamedResponse
    {
        abort_unless(
            $submission->user_id === Auth::id() || Gate::allows('manage', $submission->club),
            403,
        );

        $definition = $submission->version->schemaObject()->field($field);

        abort_unless($definition?->type === FormFieldType::Signature, 404);

        $path = $submission->answer($field);

        abort_unless(is_string($path) && $path !== '' && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, 'signature.png', [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
