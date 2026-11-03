<?php

namespace App\Livewire\News;

use App\Enums\StaffPermission;
use App\Models\Club;
use App\Models\NewsPost;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Staff write news posts for the club's members: publish now, schedule, or keep as draft.
 *
 * @property-read Collection<int, NewsPost> $posts
 */
#[Title('News')]
class Index extends Component
{
    /** Posts shown per page; "Show more" adds another page. */
    public const int PAGE = 30;

    public Club $club;

    /** Set while editing an existing post; null means the form creates one. */
    public ?int $editingPostId = null;

    public string $title = '';

    public string $body = '';

    public bool $pinned = false;

    /** now | schedule | draft */
    public string $publication = 'now';

    /** Local date and time (club timezone) for a scheduled post, "Y-m-d\TH:i". */
    public string $scheduledFor = '';

    public int $limit = self::PAGE;

    public function mount(Club $club): void
    {
        $this->authorize('access', [$club, StaffPermission::News]);

        $this->club = $club;
    }

    public function newPost(): void
    {
        $this->authorize('access', [$this->club, StaffPermission::News]);

        $this->resetForm();

        Flux::modal('news-post')->show();
    }

    public function editPost(int $postId): void
    {
        $this->authorize('access', [$this->club, StaffPermission::News]);

        $post = $this->findPost($postId);

        $this->resetForm();
        $this->editingPostId = $post->id;
        $this->title = $post->title;
        $this->body = $post->body;
        $this->pinned = $post->pinned;
        $this->publication = match (true) {
            $post->isDraft() => 'draft',
            $post->isScheduled() => 'schedule',
            default => 'now',
        };
        $this->scheduledFor = $post->isScheduled() ? $post->published_at->timezone($this->club->timezone)->format('Y-m-d\TH:i') : '';

        Flux::modal('news-post')->show();
    }

    public function savePost(): void
    {
        $this->authorize('access', [$this->club, StaffPermission::News]);

        $post = $this->editingPostId ? $this->findPost($this->editingPostId) : null;

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'pinned' => ['boolean'],
            'publication' => ['required', Rule::in(['now', 'schedule', 'draft'])],
            'scheduledFor' => ['exclude_unless:publication,schedule', 'required', 'date_format:Y-m-d\TH:i'],
        ]);

        $attributes = [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'pinned' => $validated['pinned'],
            'published_at' => match ($validated['publication']) {
                'draft' => null,
                'schedule' => Carbon::createFromFormat('Y-m-d\TH:i', $validated['scheduledFor'], $this->club->timezone)->utc(),
                // Keep the original moment when an already published post is edited.
                default => $post?->published_at !== null && ! $post->isScheduled() ? $post->published_at : now(),
            },
        ];

        if ($attributes['published_at'] !== null && $validated['publication'] === 'schedule' && $attributes['published_at']->isPast()) {
            $this->addError('scheduledFor', __('Choose a moment in the future.'));

            return;
        }

        if ($post) {
            $post->update($attributes);
        } else {
            NewsPost::create(['club_id' => $this->club->id, 'author_id' => Auth::id(), ...$attributes]);
        }

        $this->resetForm();
        unset($this->posts);

        Flux::modal('news-post')->close();
        Flux::toast(variant: 'success', text: match ($validated['publication']) {
            'draft' => __('Draft saved.'),
            'schedule' => __('Post scheduled.'),
            default => $post ? __('Post updated.') : __('Post published.'),
        });
    }

    public function togglePinned(int $postId): void
    {
        $this->authorize('access', [$this->club, StaffPermission::News]);

        $post = $this->findPost($postId);
        $post->update(['pinned' => ! $post->pinned]);

        unset($this->posts);
    }

    public function deletePost(int $postId): void
    {
        $this->authorize('access', [$this->club, StaffPermission::News]);

        $this->findPost($postId)->delete();

        unset($this->posts);

        Flux::toast(variant: 'success', text: __('Post deleted.'));
    }

    public function showMore(): void
    {
        $this->limit += self::PAGE;
    }

    /**
     * Drafts and scheduled posts first, then published posts newest first; one extra to know whether there are more.
     *
     * @return Collection<int, NewsPost>
     */
    #[Computed]
    public function posts(): Collection
    {
        return NewsPost::query()
            ->where('club_id', $this->club->id)
            ->with('author:id,name')
            ->orderByRaw('CASE WHEN published_at IS NULL OR published_at > ? THEN 0 ELSE 1 END', [now()])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($this->limit + 1)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.news.index');
    }

    /**
     * Posts of other clubs are a 404, never an edit target.
     */
    private function findPost(int $postId): NewsPost
    {
        return NewsPost::query()->where('club_id', $this->club->id)->findOrFail($postId);
    }

    private function resetForm(): void
    {
        $this->reset('editingPostId', 'title', 'body', 'pinned', 'publication', 'scheduledFor');
        $this->resetValidation();
    }
}
