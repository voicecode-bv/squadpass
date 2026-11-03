<?php

namespace App\Livewire\Portal;

use App\Enums\MembershipStatus;
use App\Models\NewsPost;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The member's news feed: published posts of every club they are an active member of.
 *
 * @property-read Collection<int, NewsPost> $pinnedPosts
 * @property-read Collection<int, NewsPost> $timeline
 */
#[Title('News')]
class News extends Component
{
    /** Posts per page in the timeline; "Show more" adds another page. */
    public const int PAGE = 20;

    public int $limit = self::PAGE;

    public function showMore(): void
    {
        $this->limit += self::PAGE;
    }

    /**
     * Pinned posts stay on top, newest first.
     *
     * @return Collection<int, NewsPost>
     */
    #[Computed]
    public function pinnedPosts(): Collection
    {
        return $this->feed()->where('pinned', true)->limit(5)->get();
    }

    /**
     * The other posts as a timeline, one extra to know whether there are more.
     *
     * @return Collection<int, NewsPost>
     */
    #[Computed]
    public function timeline(): Collection
    {
        return $this->feed()->where('pinned', false)->limit($this->limit + 1)->get();
    }

    public function render(): View
    {
        return view('livewire.portal.news');
    }

    /**
     * @return Builder<NewsPost>
     */
    private function feed(): Builder
    {
        return NewsPost::query()
            ->visible()
            ->whereIn('club_id', fn ($query) => $query
                ->select('club_id')
                ->from('memberships')
                ->where('user_id', Auth::id())
                ->where('status', MembershipStatus::Active->value))
            ->with(['club:id,name,slug,photo_path,timezone', 'author:id,name'])
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }
}
