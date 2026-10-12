<?php

namespace App\Models;

use Database\Factories\FormSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One filled in form. Rows are append-only: filling the form in again adds a
 * row and clears is_current on the previous one.
 *
 * @property int $id
 * @property int $club_id
 * @property int $form_id
 * @property int $form_version_id
 * @property int $user_id
 * @property array<string, mixed> $answers
 * @property bool $is_current
 * @property Carbon $submitted_at
 * @property Carbon|null $reopened_at
 * @property int|null $reopened_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['club_id', 'form_id', 'form_version_id', 'user_id', 'answers', 'is_current', 'submitted_at', 'reopened_at', 'reopened_by'])]
class FormSubmission extends Model
{
    /** @use HasFactory<FormSubmissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'is_current' => 'boolean',
            'submitted_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * The exact version this submission was filled in with; always use this
     * one to render it, never the form's current version.
     *
     * @return BelongsTo<FormVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * The staff member who let this response be filled in again.
     *
     * @return BelongsTo<User, $this>
     */
    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /**
     * A sent in response locks the form for that member: answers can only be
     * changed once staff reopened it, or once a newer version is published.
     */
    public function locks(Form $form): bool
    {
        return $this->is_current
            && $this->reopened_at === null
            && $this->form_version_id === $form->published_version_id;
    }

    public function answer(string $fieldKey): mixed
    {
        return $this->answers[$fieldKey] ?? null;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->where('is_current', true);
    }
}
