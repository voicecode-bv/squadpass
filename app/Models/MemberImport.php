<?php

namespace App\Models;

use App\Enums\MemberImportStatus;
use Database\Factories\MemberImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A CSV member list a club owner uploaded, processed by ProcessMemberImport.
 *
 * @property int $id
 * @property int $club_id
 * @property int|null $user_id who started the import
 * @property string $path the uploaded file on the default disk
 * @property string $original_name
 * @property array<string, int> $column_map import field => CSV column index
 * @property bool $send_invitations
 * @property MemberImportStatus $status
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $created_count new accounts created
 * @property int $linked_count existing accounts added to the club
 * @property int $skipped_count already members, or rows past the member limit
 * @property int $failed_count rows with invalid data
 * @property list<array{row: int, message: string}>|null $row_errors the first MAX_ROW_ERRORS problems
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['club_id', 'user_id', 'path', 'original_name', 'column_map', 'send_invitations', 'status', 'total_rows',
    'processed_rows', 'created_count', 'linked_count', 'skipped_count', 'failed_count', 'row_errors', 'finished_at'])]
class MemberImport extends Model
{
    /** @use HasFactory<MemberImportFactory> */
    use HasFactory;

    /**
     * Row problems kept for the report; the counts stay exact beyond this.
     */
    public const int MAX_ROW_ERRORS = 200;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'send_invitations' => 'boolean',
            'status' => MemberImportStatus::class,
            'row_errors' => 'array',
            'finished_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function progressPercentage(): int
    {
        return $this->total_rows > 0 ? (int) floor($this->processed_rows / $this->total_rows * 100) : 0;
    }
}
