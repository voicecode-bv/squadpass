<?php

use App\Enums\StaffRole;
use App\Livewire\Clubs\Index;
use App\Livewire\Clubs\Settings;
use App\Models\Club;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

test('an owner can upload a club photo', function () {
    Livewire::actingAs($this->owner)
        ->test(Settings::class, ['club' => $this->club])
        ->set('photo', UploadedFile::fake()->image('club.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $path = $this->club->refresh()->photo_path;

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('uploading a new photo replaces the previous file', function () {
    Livewire::actingAs($this->owner)
        ->test(Settings::class, ['club' => $this->club])
        ->set('photo', UploadedFile::fake()->image('first.jpg'))
        ->call('save');

    $first = $this->club->refresh()->photo_path;

    Livewire::actingAs($this->owner)
        ->test(Settings::class, ['club' => $this->club])
        ->set('photo', UploadedFile::fake()->image('second.jpg'))
        ->call('save');

    $second = $this->club->refresh()->photo_path;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

test('an owner can remove the club photo', function () {
    Livewire::actingAs($this->owner)
        ->test(Settings::class, ['club' => $this->club])
        ->set('photo', UploadedFile::fake()->image('club.jpg'))
        ->call('save');

    $path = $this->club->refresh()->photo_path;

    Livewire::actingAs($this->owner)
        ->test(Settings::class, ['club' => $this->club])
        ->call('deletePhoto');

    expect($this->club->refresh()->photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('a non-image upload is rejected', function () {
    Livewire::actingAs($this->owner)
        ->test(Settings::class, ['club' => $this->club])
        ->set('photo', UploadedFile::fake()->create('members.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['photo' => 'image']);

    expect($this->club->refresh()->photo_path)->toBeNull();
});

test('the club photo is shown on the club card', function () {
    Livewire::actingAs($this->owner)
        ->test(Settings::class, ['club' => $this->club])
        ->set('photo', UploadedFile::fake()->image('club.jpg'))
        ->call('save');

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->assertOk()
        ->assertSee($this->club->refresh()->photoUrl(), escape: false);
});
