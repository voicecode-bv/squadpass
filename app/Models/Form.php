<?php

namespace App\Models;

use App\Enums\FormAudience;
use App\Enums\FormStatus;
use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property string $name
 * @property string|null $description
 * @property FormStatus $status
 * @property FormAudience $audience
 * @property int|null $published_version_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['club_id', 'name', 'description', 'status', 'audience', 'published_version_id'])]
class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FormStatus::class,
            'audience' => FormAudience::class,
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return HasMany<FormVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    /**
     * The version members currently fill in. Null while the form has never
     * been published.
     *
     * @return BelongsTo<FormVersion, $this>
     */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'published_version_id');
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * @return HasMany<FormAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(FormAssignment::class);
    }

    /**
     * The unpublished version, if the owner is working on changes.
     */
    public function draftVersion(): ?FormVersion
    {
        return $this->versions()->whereNull('published_at')->orderByDesc('version')->first();
    }

    public function isPublished(): bool
    {
        return $this->status === FormStatus::Published && $this->published_version_id !== null;
    }

    /**
     * Forms members can still fill in.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', FormStatus::Published)->whereNotNull('published_version_id');
    }
}
