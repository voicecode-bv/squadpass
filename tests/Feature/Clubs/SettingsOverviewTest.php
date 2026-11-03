<?php

use App\Enums\StaffRole;
use App\Livewire\Clubs\SettingsOverview;
use App\Models\Club;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
});

test('the owner sees a card for every settings page', function () {
    $owner = User::factory()->create();
    $this->club->addStaff($owner, StaffRole::Owner);

    $response = $this->actingAs($owner)->get(route('clubs.settings', $this->club));

    $response->assertOk()
        ->assertSeeHtml('href="'.route('clubs.settings.club', $this->club).'"')
        ->assertSeeHtml('href="'.route('clubs.plan', $this->club).'"')
        ->assertSeeHtml('href="'.route('clubs.addons', $this->club).'"')
        ->assertSeeHtml('href="'.route('clubs.forms', $this->club).'"')
        ->assertSeeHtml('data-test="settings-card-moneybird"')
        ->assertSeeHtml('data-test="settings-card-mollie"');
});

test('integration cards lead to the add-ons page until the add-on is active', function (bool $hasAddons) {
    $owner = User::factory()->create();
    $this->club->addStaff($owner, StaffRole::Owner);
    $this->club->forceFill(['is_demo' => $hasAddons])->save();

    $cards = collect(Livewire::actingAs($owner)->test(SettingsOverview::class, ['club' => $this->club])->instance()->cards)->keyBy('key');

    expect($cards['moneybird']['url'])->toBe($hasAddons ? route('clubs.moneybird', $this->club) : route('clubs.addons', $this->club))
        ->and($cards['mollie']['url'])->toBe($hasAddons ? route('clubs.mollie', $this->club) : route('clubs.addons', $this->club));
})->with(['add-ons active' => true, 'no add-ons' => false]);

test('an admin only sees the settings they may manage', function (bool $hasMollie, array $expectedCards) {
    $admin = User::factory()->create();
    $this->club->addStaff($admin, StaffRole::Admin);
    $this->club->forceFill(['is_demo' => $hasMollie])->save();

    $cards = Livewire::actingAs($admin)->test(SettingsOverview::class, ['club' => $this->club])->instance()->cards;

    expect(array_column($cards, 'key'))->toBe($expectedCards);
})->with([
    'with Mollie' => [true, ['forms', 'trainers', 'rooms', 'mollie']],
    'without Mollie' => [false, ['forms', 'trainers', 'rooms']],
]);

test('people outside the club staff cannot open the settings', function () {
    $member = User::factory()->create();
    $this->club->addMember($member);

    $this->actingAs($member)->get(route('clubs.settings', $this->club))->assertForbidden();
});

test('the club settings form moved below the settings overview', function () {
    $owner = User::factory()->create();
    $this->club->addStaff($owner, StaffRole::Owner);

    $this->actingAs($owner)->get(route('clubs.settings.club', $this->club))
        ->assertOk()
        ->assertSee(__('Club settings'));
});

test('the navigation shows settings below the dashboard instead of the separate pages', function () {
    $owner = User::factory()->create();
    $this->club->addStaff($owner, StaffRole::Owner);

    $response = $this->actingAs($owner)->get(route('clubs.members', $this->club));

    $response->assertOk()
        ->assertSeeHtml('href="'.route('clubs.settings', $this->club).'"')
        ->assertDontSeeHtml('href="'.route('clubs.plan', $this->club).'"')
        ->assertDontSeeHtml('href="'.route('clubs.forms', $this->club).'"')
        ->assertDontSeeHtml('href="'.route('clubs.trainers', $this->club).'"')
        ->assertDontSeeHtml('href="'.route('clubs.rooms', $this->club).'"')
        ->assertDontSeeHtml('href="'.route('clubs.addons', $this->club).'"');
});

test('the settings cards are grouped under section headings', function () {
    $owner = User::factory()->create();
    $this->club->addStaff($owner, StaffRole::Owner);

    $sections = Livewire::actingAs($owner)->test(SettingsOverview::class, ['club' => $this->club])->instance()->sections;

    expect(array_map(fn (array $cards) => array_column($cards, 'key'), $sections))->toBe([
        __('Club') => ['club', 'staff', 'forms', 'trainers', 'rooms'],
        __('Plan & add-ons') => ['plan', 'addons'],
        __('Integrations') => ['moneybird', 'mollie'],
    ]);
});
