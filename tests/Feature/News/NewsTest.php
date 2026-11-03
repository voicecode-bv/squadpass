<?php

use App\Enums\StaffPermission;
use App\Enums\StaffRole;
use App\Livewire\News\Index;
use App\Livewire\Portal\News;
use App\Models\Club;
use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create(['timezone' => 'Europe/Amsterdam']);
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);
});

test('staff publish a post that members see in their feed', function () {
    Livewire::actingAs($this->owner)
        ->test(Index::class, ['club' => $this->club])
        ->call('newPost')
        ->set('title', 'Zomerrooster')
        ->set('body', "Vanaf juli trainen we op dinsdag.\nTot dan!")
        ->call('savePost')
        ->assertHasNoErrors();

    $post = NewsPost::query()->sole();
    expect($post->author_id)->toBe($this->owner->id)
        ->and($post->published_at)->not->toBeNull();

    Livewire::actingAs($this->member)
        ->test(News::class)
        ->assertSee('Zomerrooster')
        ->assertSeeHtml('Vanaf juli trainen we op dinsdag.<br />');
});

test('a scheduled post appears once its moment has come', function () {
    $this->travelTo(Carbon::parse('2026-11-03 10:00', 'Europe/Amsterdam'));

    Livewire::actingAs($this->owner)
        ->test(Index::class, ['club' => $this->club])
        ->set('title', 'Kerstsluiting')
        ->set('body', 'De club is dicht van 24 tot 26 december.')
        ->set('publication', 'schedule')
        ->set('scheduledFor', '2026-12-01T09:00')
        ->call('savePost')
        ->assertHasNoErrors();

    expect(NewsPost::query()->sole()->published_at->toIso8601String())->toBe('2026-12-01T08:00:00+00:00');
    Livewire::actingAs($this->member)->test(News::class)->assertDontSee('Kerstsluiting');

    $this->travelTo(Carbon::parse('2026-12-01 09:01', 'Europe/Amsterdam'));

    Livewire::actingAs($this->member)->test(News::class)->assertSee('Kerstsluiting');
});

test('a scheduled moment must lie in the future', function () {
    Livewire::actingAs($this->owner)
        ->test(Index::class, ['club' => $this->club])
        ->set('title', 'Te laat')
        ->set('body', 'Tekst')
        ->set('publication', 'schedule')
        ->set('scheduledFor', now()->subDay()->format('Y-m-d\TH:i'))
        ->call('savePost')
        ->assertHasErrors(['scheduledFor']);

    expect(NewsPost::query()->exists())->toBeFalse();
});

test('drafts stay out of the member feed', function () {
    NewsPost::factory()->for($this->club)->draft()->create(['title' => 'Nog niet klaar']);

    Livewire::actingAs($this->member)->test(News::class)->assertDontSee('Nog niet klaar');
});

test('members only see news of clubs they are an active member of', function () {
    NewsPost::factory()->for($this->club)->create(['title' => 'Eigen nieuws']);
    NewsPost::factory()->create(['title' => 'Ander nieuws']);

    Livewire::actingAs($this->member)
        ->test(News::class)
        ->assertSee('Eigen nieuws')
        ->assertDontSee('Ander nieuws');
});

test('pinned posts stay on top of the timeline', function () {
    NewsPost::factory()->for($this->club)->create(['title' => 'Nieuwste bericht', 'published_at' => now()->subMinute()]);
    NewsPost::factory()->for($this->club)->pinned()->create(['title' => 'Huisregels', 'published_at' => now()->subMonth()]);

    $component = Livewire::actingAs($this->member)->test(News::class);

    expect($component->instance()->pinnedPosts->pluck('title')->all())->toBe(['Huisregels'])
        ->and($component->instance()->timeline->pluck('title')->all())->toBe(['Nieuwste bericht']);
});

test('editing a published post keeps its original moment', function () {
    $post = NewsPost::factory()->for($this->club)->create(['published_at' => now()->subWeek()]);

    Livewire::actingAs($this->owner)
        ->test(Index::class, ['club' => $this->club])
        ->call('editPost', $post->id)
        ->set('title', 'Aangepaste titel')
        ->call('savePost')
        ->assertHasNoErrors();

    expect($post->fresh()->title)->toBe('Aangepaste titel')
        ->and($post->fresh()->published_at->toDateString())->toBe(now()->subWeek()->toDateString());
});

test('only staff with the news permission manage posts, and only of their own club', function () {
    $desk = User::factory()->create();
    $this->club->addStaff($desk, StaffRole::Admin, [StaffPermission::Members]);
    $foreignPost = NewsPost::factory()->create();

    $this->actingAs($desk)->get(route('clubs.news', $this->club))->assertForbidden();

    Livewire::actingAs($this->owner)
        ->test(Index::class, ['club' => $this->club])
        ->call('deletePost', $foreignPost->id)
        ->assertNotFound();

    expect($foreignPost->fresh())->not->toBeNull();
});

test('the member app reads the news feed through the api', function () {
    NewsPost::factory()->for($this->club)->create(['title' => 'Via de app']);
    NewsPost::factory()->for($this->club)->draft()->create(['title' => 'Concept']);
    Sanctum::actingAs($this->member);

    $this->getJson('/api/v1/news')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Via de app')
        ->assertJsonPath('data.0.club_name', $this->club->name);
});

test('news sits right below the dashboard in the navigation', function (string $who, string $route) {
    $user = $who === 'owner' ? $this->owner : $this->member;
    $url = $route === 'clubs.news' ? route('clubs.news', $this->club) : route('portal.news');

    $content = $this->actingAs($user)->get(route('portal.enrollments'))->assertOk()->getContent();

    $newsLink = strpos($content, 'href="'.$url.'"');
    expect($newsLink)->toBeGreaterThan(strpos($content, '>'.__('Dashboard')))
        ->and($newsLink)->toBeLessThan(strpos($content, 'data-sidebar-group='));
})->with([
    'owner opens the club news' => ['owner', 'clubs.news'],
    'member opens their feed' => ['member', 'portal.news'],
]);
