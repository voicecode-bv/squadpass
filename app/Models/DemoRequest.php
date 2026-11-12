<?php

namespace App\Models;

use App\Enums\ClubSegment;
use Database\Factories\DemoRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A visitor who opened the demo, with the email address and type of club
 * they left in the demo modal. Either the segment or the free-text type is
 * set: the latter when they chose "Something else".
 *
 * @property int $id
 * @property string $email
 * @property ClubSegment|null $club_segment
 * @property string|null $other_club_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['email', 'club_segment', 'other_club_type'])]
class DemoRequest extends Model
{
    /** @use HasFactory<DemoRequestFactory> */
    use HasFactory;

    /** Value of the "Something else" option next to the segments in the demo modal. */
    public const string OTHER_CLUB_TYPE = 'other';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'club_segment' => ClubSegment::class,
        ];
    }

    /**
     * The type of club as the visitor gave it.
     */
    public function clubTypeLabel(): string
    {
        return $this->club_segment?->singularLabel() ?? (string) $this->other_club_type;
    }
}
