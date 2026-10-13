<?php

use App\Enums\StaffRole;
use App\Models\Club;
use App\Models\User;

test('the sidebar groups are collapsible and carry a stable storage key', function () {
    $club = Club::factory()->create();
    $staff = User::factory()->create();
    $club->addStaff($staff, StaffRole::Owner);
    $club->addMember($staff);

    $response = $this->actingAs($staff)->get(route('clubs.members', $club));

    $response->assertOk()
        ->assertSee('data-sidebar-group="platform"', escape: false)
        ->assertSee('data-sidebar-group="membership"', escape: false)
        ->assertSee('data-sidebar-group="club-members"', escape: false)
        // The inline script restores the remembered state before Flux boots the disclosures.
        ->assertSee("localStorage.getItem('sidebar-group:'", escape: false);
});

test('the sidebar groups are collapsed by default', function () {
    $club = Club::factory()->create();
    $staff = User::factory()->create();
    $club->addStaff($staff, StaffRole::Owner);

    $content = $this->actingAs($staff)->get(route('clubs.members', $club))->assertOk()->getContent();

    // The inline script opens the group holding the current page client-side.
    expect($content)->toContain('data-sidebar-group="club-members"')
        ->and($content)->not->toMatch('/<ui-disclosure[^>]*\sopen[\s>]/');
});

test('the header follows the sidebar so the logo sits next to the search bar', function () {
    $user = User::factory()->create();

    $content = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    // Flux only puts the sidebar beside the header when they are siblings in this order.
    expect($content)->toMatch('/<\/ui-sidebar>\s*<header/');
});
