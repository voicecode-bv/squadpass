<?php

namespace App\Actions\Forms;

use App\Enums\FormStatus;
use App\Exceptions\FormException;
use App\Models\Form;
use App\Models\FormVersion;
use Illuminate\Support\Facades\DB;

class PublishFormVersion
{
    /**
     * Make the draft the version members fill in from now on. Existing
     * submissions keep pointing at their own version.
     */
    public function handle(Form $form, FormVersion $draft): FormVersion
    {
        throw_unless($draft->form_id === $form->id, FormException::notPublished());
        throw_if($draft->schemaObject()->isEmpty(), FormException::emptySchema());

        return DB::transaction(function () use ($form, $draft): FormVersion {
            $draft->update(['published_at' => now()]);

            $form->update([
                'published_version_id' => $draft->id,
                'status' => FormStatus::Published,
            ]);

            return $draft;
        });
    }
}
