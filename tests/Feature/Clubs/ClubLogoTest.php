<?php

use App\Enums\StaffRole;
use App\Livewire\Clubs\Settings;
use App\Mail\ClubInvitationMail;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    $this->club = Club::factory()->create(['name' => 'Judo Oost']);
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

test('an owner uploads a logo that replaces the previous one', function () {
    $component = Livewire::actingAs($this->owner)->test(Settings::class, ['club' => $this->club]);

    $component->set('logo', UploadedFile::fake()->image('first.png'))->call('save')->assertHasNoErrors();
    $first = $this->club->refresh()->logo_path;

    $component->set('logo', UploadedFile::fake()->image('second.png'))->call('save')->assertHasNoErrors();
    $second = $this->club->refresh()->logo_path;

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

test('an owner uploads and removes a dark-mode logo next to the light one', function () {
    $component = Livewire::actingAs($this->owner)->test(Settings::class, ['club' => $this->club]);

    $component->set('logo', UploadedFile::fake()->image('light.png'))
        ->set('darkLogo', UploadedFile::fake()->image('dark.png'))
        ->call('save')
        ->assertHasNoErrors();

    $this->club->refresh();
    $darkPath = $this->club->logo_dark_path;

    expect($darkPath)->not->toBeNull()->not->toBe($this->club->logo_path);
    Storage::disk('public')->assertExists($darkPath);

    $component->call('deleteDarkLogo');

    expect($this->club->refresh()->logo_dark_path)->toBeNull()
        ->and($this->club->logo_path)->not->toBeNull();
    Storage::disk('public')->assertMissing($darkPath);
});

test('an owner removes the logo', function () {
    $component = Livewire::actingAs($this->owner)->test(Settings::class, ['club' => $this->club]);
    $component->set('logo', UploadedFile::fake()->image('logo.png'))->call('save');
    $path = $this->club->refresh()->logo_path;

    $component->call('deleteLogo');

    expect($this->club->refresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('the email logo only accepts formats every email client shows', function (UploadedFile $file) {
    Livewire::actingAs($this->owner)
        ->test(Settings::class, ['club' => $this->club])
        ->set('logo', $file)
        ->call('save')
        ->assertHasErrors('logo')
        ->assertSet('tab', 'general');

    expect($this->club->refresh()->logo_path)->toBeNull();
})->with([
    'svg' => fn () => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    'webp' => fn () => UploadedFile::fake()->image('logo.webp'),
    'pdf' => fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
]);

test('club emails are headed by the club logo, or the club name without one', function () {
    $invitation = ClubInvitation::factory()->create(['club_id' => $this->club->id]);

    expect((new ClubInvitationMail($invitation))->render())
        ->toContain('<span class="club-name"')
        ->toContain('Judo Oost')
        ->not->toContain('club-logo');

    $this->club->update(['logo_path' => 'club-logos/judo-oost.png']);

    expect((new ClubInvitationMail($invitation->fresh()))->render())
        ->toContain('src="'.$this->club->logoUrl().'"')
        ->not->toContain('<span class="club-name"');
});

test('account emails without a club carry the SquadPass mark', function () {
    $html = (string) (new ResetPassword('token'))->toMail($this->owner)->render();

    expect($html)->toContain(asset('images/logos/squadpass-mark.png'))
        ->not->toContain('club-logo');
});

test('the sidebar shows the club logos with a powered-by line once a logo is uploaded', function () {
    $this->actingAs($this->owner)->get(route('clubs.dashboard', $this->club))
        ->assertOk()
        ->assertSee('images/logos/squadpass-lockup.svg')
        ->assertDontSee(__('Powered by :app', ['app' => config('app.name')]));

    $this->club->update(['logo_path' => 'club-logos/light.png']);

    // Without a dark variant, dark mode falls back to the light logo.
    $html = $this->actingAs($this->owner)->get(route('clubs.dashboard', $this->club))
        ->assertOk()
        ->assertSee(__('Powered by :app', ['app' => config('app.name')]))
        ->assertDontSee('images/logos/squadpass-lockup.svg')
        ->getContent();

    expect(substr_count($html, $this->club->logoUrl()))->toBe(2);

    $this->club->update(['logo_dark_path' => 'club-logos/dark.png']);

    $this->actingAs($this->owner)->get(route('clubs.dashboard', $this->club))
        ->assertOk()
        ->assertSeeInOrder([$this->club->logoUrl(), $this->club->darkLogoUrl()]);
});

test('emails always use the light logo', function () {
    $this->club->update(['logo_path' => 'club-logos/light.png', 'logo_dark_path' => 'club-logos/dark.png']);
    $invitation = ClubInvitation::factory()->create(['club_id' => $this->club->id]);

    expect((new ClubInvitationMail($invitation))->render())
        ->toContain($this->club->logoUrl())
        ->not->toContain($this->club->darkLogoUrl());
});
