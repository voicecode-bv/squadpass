<?php

use App\Enums\StaffRole;
use App\Livewire\Clubs\Settings;
use App\Mail\ClubInvitationMail;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\User;
use App\Services\BrandPalette;
use Illuminate\Auth\Notifications\ResetPassword;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

test('an owner sets and clears the brand colour', function () {
    $component = Livewire::actingAs($this->owner)->test(Settings::class, ['club' => $this->club])
        ->set('custom_brand_color', true)
        ->set('brand_color', '#E11D48')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->club->refresh()->brand_color)->toBe('#e11d48');

    $component->set('custom_brand_color', false)->call('save')->assertHasNoErrors();

    expect($this->club->refresh()->brand_color)->toBeNull();
});

test('a brand colour that is not #rrggbb is rejected', function () {
    Livewire::actingAs($this->owner)->test(Settings::class, ['club' => $this->club])
        ->set('custom_brand_color', true)
        ->set('brand_color', 'red; } body { display: none')
        ->call('save')
        ->assertHasErrors('brand_color');

    expect($this->club->refresh()->brand_color)->toBeNull();
});

test('club pages repaint the blue palette in the brand colour', function () {
    $this->actingAs($this->owner)->get(route('clubs.dashboard', $this->club))
        ->assertOk()
        ->assertDontSee('data-club-brand', escape: false);

    $this->club->update(['brand_color' => '#e11d48']);

    $this->actingAs($this->owner)->get(route('clubs.dashboard', $this->club))
        ->assertOk()
        ->assertSee('--color-blue-600: '.BrandPalette::fromHex('#e11d48')[600].';', escape: false);
});

test('the public trial page wears the club colour', function () {
    $this->club->update(['brand_color' => '#e11d48', 'trial_lessons_enabled' => true]);

    $this->get(route('clubs.trial', $this->club))
        ->assertOk()
        ->assertSee('data-club-brand', escape: false);
});

test('the member portal wears the colour of the only club, and blue across several clubs', function () {
    $this->club->update(['brand_color' => '#e11d48']);
    $member = User::factory()->create();
    $this->club->addMember($member);

    $this->actingAs($member)->get(route('portal.schedule'))
        ->assertOk()
        ->assertSee('data-club-brand', escape: false);

    Club::factory()->create()->addMember($member);

    $this->actingAs($member)->get(route('portal.schedule'))
        ->assertOk()
        ->assertDontSee('data-club-brand', escape: false);
});

test('club emails use the brand colour and other emails keep SquadPass blue', function () {
    $this->club->update(['brand_color' => '#e11d48']);
    $brand600 = BrandPalette::fromHex('#e11d48')[600];
    $invitation = ClubInvitation::factory()->create(['club_id' => $this->club->id]);

    expect((new ClubInvitationMail($invitation))->render())
        ->toContain('background-color: '.$brand600)
        ->not->toContain(BrandPalette::DEFAULT_COLOR);

    expect((string) (new ResetPassword('token'))->toMail($this->owner)->render())
        ->toContain('background-color: '.BrandPalette::DEFAULT_COLOR);
});
