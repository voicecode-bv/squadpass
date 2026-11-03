<?php

use App\Enums\MembershipStatus;
use App\Enums\StaffRole;
use App\Livewire\News\Index;
use App\Models\Club;
use App\Models\NewsPost;
use App\Models\User;
use App\Notifications\NewsPublished;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->freezeTime();

    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);
});

test('active members are emailed once about a post published with email members on', function () {
    Notification::fake();
    $formerMember = User::factory()->create();
    $this->club->addMember($formerMember)->update(['status' => MembershipStatus::Ended]);

    Livewire::actingAs($this->owner)
        ->test(Index::class, ['club' => $this->club])
        ->set('title', 'Zomerrooster')
        ->set('body', 'Vanaf juli trainen we op dinsdag.')
        ->set('notify_members', true)
        ->call('savePost')
        ->assertHasNoErrors();

    $this->artisan('news:notify')->assertSuccessful();
    $this->artisan('news:notify')->assertSuccessful();

    Notification::assertSentToTimes($this->member, NewsPublished::class, 1);
    Notification::assertNotSentTo($formerMember, NewsPublished::class);
});

test('a scheduled post is emailed only once it is published', function () {
    Notification::fake();
    $post = NewsPost::factory()->for($this->club)->create(['notify_members' => true, 'published_at' => now()->addHour()]);

    $this->artisan('news:notify')->assertSuccessful();
    Notification::assertNothingSent();

    $this->travel(61)->minutes();
    $this->artisan('news:notify')->assertSuccessful();

    Notification::assertSentTo($this->member, NewsPublished::class, fn (NewsPublished $notification) => $notification->post->is($post));
});

test('posts without email members are not emailed', function () {
    Notification::fake();
    NewsPost::factory()->for($this->club)->create(['notify_members' => false, 'published_at' => now()]);

    $this->artisan('news:notify')->assertSuccessful();

    Notification::assertNothingSent();
});
