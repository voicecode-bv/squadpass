<?php

use App\Models\User;

test('a login link logs the user in when the environment is allowed', function () {
    config(['login-link.allowed_environments' => ['testing']]);

    $user = User::factory()->create(['email' => 'owner@example.com']);

    $this->post(route('loginLinkLogin'), ['email' => 'owner@example.com'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

test('a login link does not create missing users', function () {
    config(['login-link.allowed_environments' => ['testing']]);

    $this->post(route('loginLinkLogin'), ['email' => 'onbekend@example.com']);

    expect(User::query()->where('email', 'onbekend@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

test('login links are rejected outside allowed environments', function () {
    User::factory()->create(['email' => 'owner@example.com']);

    $this->post(route('loginLinkLogin'), ['email' => 'owner@example.com'])
        ->assertServerError();

    $this->assertGuest();
});

test('the login screen renders without dev links outside the local environment', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee(__('Development logins'));
});
