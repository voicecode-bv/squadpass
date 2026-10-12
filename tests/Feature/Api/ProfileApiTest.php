<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->member = User::factory()->create([
        'name' => 'Sanne de Vries',
        'email' => 'sanne@example.com',
        'password' => Hash::make('oud-wachtwoord'),
    ]);

    Sanctum::actingAs($this->member);
});

test('a member can update their own name and email', function () {
    $this->patchJson('/api/v1/profile', ['name' => 'Sanne Bakker', 'email' => 'sanne.bakker@example.com'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Sanne Bakker');

    expect($this->member->refresh()->email)->toBe('sanne.bakker@example.com')
        ->and($this->member->email_verified_at)->toBeNull();
});

test('an email address already in use is refused', function () {
    User::factory()->create(['email' => 'bezet@example.com']);

    $this->patchJson('/api/v1/profile', ['name' => 'Sanne', 'email' => 'bezet@example.com'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('a member can change their password with the current one', function () {
    $this->putJson('/api/v1/profile/password', [
        'current_password' => 'oud-wachtwoord',
        'password' => 'nieuw-geheim-wachtwoord',
        'password_confirmation' => 'nieuw-geheim-wachtwoord',
    ])->assertOk();

    expect(Hash::check('nieuw-geheim-wachtwoord', $this->member->refresh()->password))->toBeTrue();
});

test('a wrong current password leaves the password untouched', function () {
    $this->putJson('/api/v1/profile/password', [
        'current_password' => 'fout',
        'password' => 'nieuw-geheim-wachtwoord',
        'password_confirmation' => 'nieuw-geheim-wachtwoord',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('current_password');

    expect(Hash::check('oud-wachtwoord', $this->member->refresh()->password))->toBeTrue();
});
