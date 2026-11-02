<?php

use App\Enums\StaffRole;
use App\Livewire\ProductTour;
use App\Models\Club;
use App\Models\User;
use App\Services\Impersonation\Impersonation;
use Livewire\Livewire;

test('a new user sees the tour on the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Welcome to SquadPass'))
        ->assertSee(__('How SquadPass works'));
});

test('closing the tour marks it as completed so it does not open again', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProductTour::class)
        ->assertSet('open', true)
        ->call('complete')
        ->assertSet('open', false);

    expect($user->fresh()->product_tour_completed_at)->not->toBeNull();

    Livewire::actingAs($user->fresh())
        ->test(ProductTour::class)
        ->assertSet('open', false)
        ->assertDontSee(__('Welcome to SquadPass'));
});

test('a user who completed the tour can reopen it', function () {
    $user = User::factory()->create(['product_tour_completed_at' => now()->subWeek()]);

    Livewire::actingAs($user)
        ->test(ProductTour::class)
        ->assertSet('open', false)
        ->dispatch('open-product-tour')
        ->assertSet('open', true)
        ->assertSee(__('Welcome to SquadPass'));
});

test('staff get the club tour with links into their club', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create();
    $club->addStaff($user, StaffRole::Owner);

    Livewire::actingAs($user)
        ->test(ProductTour::class)
        ->assertSee(__('Set up your club'))
        ->assertSee(__('Check-in desk'))
        ->assertDontSee(__('Find a lesson'))
        ->call('visit', 1)
        ->assertRedirect(route('clubs.onboarding', $club));

    expect($user->fresh()->product_tour_completed_at)->not->toBeNull();
});

test('members get the member tour with links into their portal', function () {
    $user = User::factory()->create();
    Club::factory()->create()->addMember($user);

    Livewire::actingAs($user)
        ->test(ProductTour::class)
        ->assertSee(__('Find a lesson'))
        ->assertDontSee(__('Set up your club'))
        ->call('visit', 1)
        ->assertRedirect(route('portal.schedule'));
});

test('users without a club get the explanation without links', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProductTour::class)
        ->assertSee(__('Find a lesson'))
        ->assertDontSee(__('View lesson schedule'))
        ->call('visit', 1)
        ->assertNotFound();
});

test('impersonating staff do not open or use up the tour of a new member', function () {
    $staff = User::factory()->create(['product_tour_completed_at' => now()]);
    $member = User::factory()->create();

    $this->actingAs($staff);
    Impersonation::start($member, route('dashboard'));

    Livewire::test(ProductTour::class)
        ->assertSet('open', false)
        ->dispatch('open-product-tour')
        ->call('complete');

    expect($member->fresh()->product_tour_completed_at)->toBeNull();
});
