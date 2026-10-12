<?php

namespace App\Actions\Forms;

use App\Models\Form;
use App\Models\FormVersion;
use Illuminate\Support\Facades\DB;

class StartFormDraft
{
    /**
     * Return the form's open draft, or start a new one by copying the
     * published schema. Published versions are never edited in place, so
     * submissions keep rendering with the schema they were filled in with.
     */
    public function handle(Form $form): FormVersion
    {
        return DB::transaction(function () use ($form): FormVersion {
            $draft = $form->versions()->whereNull('published_at')->orderByDesc('version')->lockForUpdate()->first();

            if ($draft) {
                return $draft;
            }

            $latest = $form->versions()->orderByDesc('version')->first();

            return FormVersion::create([
                'form_id' => $form->id,
                'version' => ($latest->version ?? 0) + 1,
                'schema' => $latest->schema ?? ['fields' => []],
                'published_at' => null,
            ]);
        });
    }
}
