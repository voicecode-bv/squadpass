<?php

use App\Enums\StaffRole;
use App\Models\Club;
use App\Models\User;

test('the sidebar groups are always open, club groups first and the platform group last', function () {
    $club = Club::factory()->create();
    $staff = User::factory()->superadmin()->create();
    $club->addStaff($staff, StaffRole::Owner);
    $club->addMember($staff);

    $content = $this->actingAs($staff)->get(route('clubs.members', $club))->assertOk()->getContent();

    expect($content)->not->toContain('<ui-disclosure')
        ->and($content)->not->toContain("localStorage.getItem('sidebar-group:'")
        ->and(strpos($content, 'href="'.route('clubs.settings', $club).'"'))->toBeLessThan(strpos($content, 'data-sidebar-group="club-members"'))
        ->and(strpos($content, 'data-sidebar-group="club-billing"'))->toBeLessThan(strpos($content, 'data-sidebar-group="membership"'))
        ->and(strpos($content, 'data-sidebar-group="membership"'))->toBeLessThan(strpos($content, 'data-sidebar-group="platform"'));
});

test('the header follows the sidebar so the logo sits next to the search bar', function () {
    $user = User::factory()->create();

    $content = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    // Flux only puts the sidebar beside the header when they are siblings in this order.
    expect($content)->toMatch('/<\/ui-sidebar>\s*<header/');
});

test('staff pick their club from a switcher in the top bar', function () {
    $club = Club::factory()->create(['name' => 'Boksclub Noord']);
    $otherClub = Club::factory()->create(['name' => 'Judo Zuid']);
    $strangerClub = Club::factory()->create(['name' => 'Andere Club']);
    $staff = User::factory()->create();
    $club->addStaff($staff, StaffRole::Owner);
    $otherClub->addStaff($staff, StaffRole::Admin);

    $response = $this->actingAs($staff)->get(route('clubs.members', $club));

    $response->assertOk()
        ->assertSee('data-test="club-switcher"', escape: false)
        ->assertSee(route('clubs.dashboard', $otherClub))
        ->assertDontSee('Andere Club');
});

test('the switcher asks to choose a club outside a club page', function () {
    $staff = User::factory()->create();
    Club::factory()->create()->addStaff($staff, StaffRole::Owner);
    Club::factory()->create()->addStaff($staff, StaffRole::Owner);

    $this->actingAs($staff)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Choose a club'));
});

test('members without a staff role get no club switcher', function () {
    $club = Club::factory()->create();
    $member = User::factory()->create();
    $club->addMember($member);

    $this->actingAs($member)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('data-test="club-switcher"', escape: false);
});

test('the platform group is only shown to superadmins', function (bool $isSuperadmin) {
    $club = Club::factory()->create();
    $user = User::factory()->create(['is_superadmin' => $isSuperadmin]);
    $club->addStaff($user, StaffRole::Owner);

    $response = $this->actingAs($user)->get(route('clubs.members', $club))->assertOk();

    $isSuperadmin
        ? $response->assertSee('data-sidebar-group="platform"', escape: false)
        : $response->assertDontSee('data-sidebar-group="platform"', escape: false);
})->with(['superadmin' => true, 'club owner' => false]);

test('a superadmin picks from every club, with the open club always listed', function () {
    Club::factory()->count(User::CLUB_SWITCHER_LIMIT + 1)->sequence(fn ($sequence) => ['name' => 'Club '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT)])->create();
    $lastClub = Club::factory()->create(['name' => 'Zwemclub West']);
    $superadmin = User::factory()->superadmin()->create();

    $response = $this->actingAs($superadmin)->get(route('clubs.dashboard', $lastClub));

    $response->assertOk()
        ->assertSee(route('clubs.dashboard', Club::query()->where('name', 'Club 00')->sole()))
        ->assertSee('Zwemclub West')
        ->assertSee(__('All clubs'));
});

test('a single club is selected by default and its dashboard is the top item', function () {
    $club = Club::factory()->create(['name' => 'Enige Club']);
    $owner = User::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);

    $this->actingAs($owner)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Enige Club')
        ->assertDontSee(__('Choose a club'))
        ->assertSee('data-sidebar-group="club-members"', escape: false)
        ->assertSee('href="'.route('clubs.dashboard', $club).'"', escape: false);
});
