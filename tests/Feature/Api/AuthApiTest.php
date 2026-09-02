<?php

use App\Models\User;

test('a member can log in and use the token', function () {
    $user = User::factory()->create(['email' => 'lid@example.com']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'lid@example.com',
        'password' => 'password',
        'device_name' => 'iPhone van lid',
    ]);

    $response->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

    $this->withHeader('Authorization', 'Bearer '.$response->json('token'))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

test('logging in with a wrong password fails', function () {
    User::factory()->create(['email' => 'lid@example.com']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'lid@example.com',
        'password' => 'verkeerd',
        'device_name' => 'iPhone van lid',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

test('logging out revokes the token', function () {
    User::factory()->create(['email' => 'lid@example.com']);

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'lid@example.com',
        'password' => 'password',
        'device_name' => 'iPhone van lid',
    ])->json('token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    // Forget the cached guard so the next request re-authenticates the token.
    app('auth')->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});

test('guests get 401 on protected endpoints', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
})->with([
    'me' => '/api/v1/me',
    'clubs' => '/api/v1/clubs',
    'schedule' => '/api/v1/schedule',
    'enrollments' => '/api/v1/enrollments',
    'strip cards' => '/api/v1/strip-cards',
    'subscriptions' => '/api/v1/subscriptions',
    'visits' => '/api/v1/visits',
    'tickets' => '/api/v1/tickets',
]);
