<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('a user can upload a cropped avatar', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('avatar', UploadedFile::fake()->image('avatar.jpg', 512, 512))
        ->call('saveAvatar')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->avatar_path)->not->toBeNull()
        ->and($user->avatarUrl())->toContain('avatars/');

    Storage::disk('public')->assertExists($user->avatar_path);
});

test('uploading a new avatar replaces the old file', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Profile::class);

    $component->set('avatar', UploadedFile::fake()->image('first.jpg'))->call('saveAvatar');
    $firstPath = $user->refresh()->avatar_path;

    $component->set('avatar', UploadedFile::fake()->image('second.jpg'))->call('saveAvatar');

    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($user->refresh()->avatar_path);
});

test('a non-image upload is rejected', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('avatar', UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
        ->call('saveAvatar')
        ->assertHasErrors(['avatar']);

    expect($user->refresh()->avatar_path)->toBeNull();
});

test('a user can remove their avatar', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(Profile::class);
    $component->set('avatar', UploadedFile::fake()->image('avatar.jpg'))->call('saveAvatar');
    $path = $user->refresh()->avatar_path;

    $component->call('deleteAvatar');

    expect($user->refresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});
