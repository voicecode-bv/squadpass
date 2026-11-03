<?php

namespace App\Models;

use Database\Factories\NewsPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A message from the club to its members, shown in the members' news feed.
 *
 * @property int $id
 * @property int $club_id
 * @property int|null $author_id
 * @property string $title
 * @property string $body
 * @property bool $pinned
 * @property bool $notify_members email the club's members once the post is published
 * @property Carbon|null $members_notified_at set once that email went out
 * @property Carbon|null $published_at null for a draft; in the future when scheduled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['club_id', 'author_id', 'title', 'body', 'pinned', 'notify_members', 'members_notified_at', 'published_at'])]
class NewsPost extends Model
{
    /** @use HasFactory<NewsPostFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
            'notify_members' => 'boolean',
            'members_notified_at' => 'datetime',
            'published_at' => 'datetime',
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    public function isScheduled(): bool
    {
        return $this->published_at !== null && $this->published_at->isFuture();
    }

    /**
     * Posts members can read now.
     *
     * @param  Builder<self>  $query
     */
    protected function scopeVisible(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
