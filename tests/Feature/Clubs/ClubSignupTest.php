<?php

use App\Enums\StaffRole;
use App\Livewire\Clubs\Signup;
use App\Models\Club;
use App\Models\User;
use App\Services\SpamProtection;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * A filled-in signup form, submitted after a human-like pause.
 */
function filledClubSignup(): Testable
{
    $component = Livewire::test(Signup::class)
        ->set('club_name', 'Padel Club Amsterdam')
        ->set('name', 'Alex Janssen')
        ->set('email', 'alex@example.com')
        ->set('password', 'password');

    test()->travel(SpamProtection::MINIMUM_SECONDS)->seconds();

    return $component;
}

test('the landing page shows the club signup form to guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeLivewire(Signup::class);
});

test('the landing page does not show the signup form to logged in users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertDontSeeLivewire(Signup::class);
});

test('a guest creates an account and club in one step and continues to the onboarding', function () {
    Event::fake([Registered::class]);

    filledClubSignup()
        ->call('signup')
        ->assertHasNoErrors()
        ->assertRedirect(route('clubs.onboarding', 'padel-club-amsterdam'));

    $user = User::query()->where('email', 'alex@example.com')->firstOrFail();
    $club = Club::query()->where('slug', 'padel-club-amsterdam')->firstOrFail();

    expect($user->isStaffOf($club, StaffRole::Owner))->toBeTrue()
        ->and(session('url.intended'))->toBe(route('clubs.onboarding', $club));

    $this->assertAuthenticatedAs($user);
    Event::assertDispatched(Registered::class);
});

test('signing up requires a club name and a unique email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test(Signup::class)
        ->set('name', 'Alex Janssen')
        ->set('email', 'taken@example.com')
        ->set('password', 'password')
        ->call('signup')
        ->assertHasErrors(['club_name' => 'required', 'email' => 'unique']);

    expect(Club::query()->count())->toBe(0);
    $this->assertGuest();
});

test('club signups are rate limited per ip address', function () {
    foreach (range(1, Signup::MAX_ATTEMPTS_PER_MINUTE) as $attempt) {
        Livewire::test(Signup::class)->call('signup');
    }

    filledClubSignup()
        ->call('signup')
        ->assertHasErrors('email');

    expect(User::query()->where('email', 'alex@example.com')->exists())->toBeFalse();
});

test('a signup with the honeypot filled in is rejected', function () {
    filledClubSignup()
        ->set('website', 'https://spam.example')
        ->call('signup')
        ->assertHasErrors(SpamProtection::ERROR_KEY)
        ->assertDispatched('turnstile-reset');

    expect(User::query()->count())->toBe(0);
});

test('a signup submitted faster than a person can type is rejected', function () {
    Livewire::test(Signup::class)
        ->set('club_name', 'Padel Club Amsterdam')
        ->set('name', 'Alex Janssen')
        ->set('email', 'alex@example.com')
        ->set('password', 'password')
        ->call('signup')
        ->assertHasErrors(SpamProtection::ERROR_KEY);

    expect(User::query()->count())->toBe(0);
});

test('the form start time cannot be changed from the browser', function () {
    Livewire::test(Signup::class)
        ->set('formStartedAt', 'forged');
})->throws(CannotUpdateLockedPropertyException::class);

test('turnstile must confirm the visitor when it is configured', function (bool $turnstileSuccess) {
    config(['services.turnstile.site_key' => 'site-key', 'services.turnstile.secret_key' => 'secret-key']);
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => $turnstileSuccess])]);

    $component = filledClubSignup()->call('signup', 'turnstile-token');

    $turnstileSuccess
        ? $component->assertHasNoErrors()->assertRedirect()
        : $component->assertHasErrors(SpamProtection::ERROR_KEY);

    expect(User::query()->where('email', 'alex@example.com')->exists())->toBe($turnstileSuccess);
    Http::assertSent(fn ($request) => $request['secret'] === 'secret-key' && $request['response'] === 'turnstile-token');
})->with([
    'verified' => true,
    'rejected' => false,
]);

test('a signup without a turnstile token is rejected when turnstile is configured', function () {
    config(['services.turnstile.site_key' => 'site-key', 'services.turnstile.secret_key' => 'secret-key']);
    Http::fake();

    filledClubSignup()
        ->call('signup')
        ->assertHasErrors(SpamProtection::ERROR_KEY);

    Http::assertNothingSent();
});
