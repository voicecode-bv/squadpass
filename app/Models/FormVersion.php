<?php

namespace App\Models;

use App\Services\Forms\FormSchema;
use Database\Factories\FormVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An immutable snapshot of a form's fields. Published versions are never
 * edited, so submissions that point at them keep rendering as they were
 * filled in.
 *
 * @property int $id
 * @property int $form_id
 * @property int $version
 * @property array<string, mixed> $schema
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['form_id', 'version', 'schema', 'published_at'])]
class FormVersion extends Model
{
    /** @use HasFactory<FormVersionFactory> */
    use HasFactory;

    private ?FormSchema $parsedSchema = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'version' => 'integer',
            'published_at' => 'datetime',
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
     * Parsed schema, resolved once per instance.
     */
    public function schemaObject(): FormSchema
    {
        return $this->parsedSchema ??= FormSchema::fromArray($this->schema ?? []);
    }

    public function setSchema(FormSchema $schema): void
    {
        $this->parsedSchema = null;
        $this->schema = $schema->toArray();
    }

    public function isDraft(): bool
    {
        return $this->published_at === null;
    }
}
