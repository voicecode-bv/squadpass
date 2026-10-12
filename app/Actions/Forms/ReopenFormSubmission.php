<?php

namespace App\Actions\Forms;

use App\Models\FormSubmission;
use App\Models\User;

class ReopenFormSubmission
{
    /**
     * Let the member fill this form in again. The response stays untouched as
     * history, it simply stops locking the form.
     */
    public function handle(FormSubmission $submission, User $staff): void
    {
        $submission->update([
            'reopened_at' => now(),
            'reopened_by' => $staff->id,
        ]);
    }
}
