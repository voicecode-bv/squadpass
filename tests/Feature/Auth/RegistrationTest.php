<?php

use App\Services\SpamProtection;
use Illuminate\Support\Facades\Crypt;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $startedAt = app(SpamProtection::class)->startedAtToken();
    $this->travel(SpamProtection::MINIMUM_SECONDS)->seconds();

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        SpamProtection::STARTED_AT_FIELD => $startedAt,
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('registrations from bots are rejected', function (array $botFields) {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$botFields,
    ]);

    $response->assertSessionHasErrors(SpamProtection::ERROR_KEY);

    $this->assertGuest();
})->with([
    'honeypot filled in' => fn () => [
        SpamProtection::HONEYPOT_FIELD => 'https://spam.example',
        SpamProtection::STARTED_AT_FIELD => Crypt::encryptString((string) now()->subMinute()->timestamp),
    ],
    'submitted instantly' => fn () => [SpamProtection::STARTED_AT_FIELD => app(SpamProtection::class)->startedAtToken()],
    'start time missing' => fn () => [],
    'start time forged' => fn () => [SpamProtection::STARTED_AT_FIELD => '1'],
]);
