<?php

use App\Livewire\Clubs\Signup;
use App\Livewire\Clubs\StartDemo;
use App\Services\AnalyticsEvents;
use App\Services\SpamProtection;
use Livewire\Livewire;

test('a club signup queues the sign_up event for google analytics', function () {
    $component = Livewire::test(Signup::class)
        ->set('club_name', 'Padel Club Amsterdam')
        ->set('first_name', 'Alex')
        ->set('last_name', 'Janssen')
        ->set('email', 'alex@example.com')
        ->set('password', 'password');
    $this->travel(SpamProtection::MINIMUM_SECONDS)->seconds();

    $component->call('signup')->assertHasNoErrors();

    expect(session(AnalyticsEvents::SESSION_KEY))->toBe([
        ['name' => AnalyticsEvents::SIGN_UP, 'parameters' => ['method' => 'club_signup']],
    ]);
});

test('starting a demo queues the demo_start event for google analytics', function () {
    $component = Livewire::test(StartDemo::class);
    $this->travel(SpamProtection::MINIMUM_SECONDS + 1)->seconds();

    $component->call('start')->assertHasNoErrors();

    expect(session(AnalyticsEvents::SESSION_KEY))->toBe([
        ['name' => AnalyticsEvents::DEMO_START, 'parameters' => []],
    ]);
});

test('queued events are sent with the next page in production and only once', function () {
    app()->detectEnvironment(fn (): string => 'production');
    $this->withSession([AnalyticsEvents::SESSION_KEY => [
        ['name' => AnalyticsEvents::SIGN_UP, 'parameters' => ['method' => 'club_signup']],
    ]]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee("gtag('event', 'sign_up'", false);

    expect(session()->has(AnalyticsEvents::SESSION_KEY))->toBeFalse();

    $this->get(route('home'))->assertDontSee("gtag('event', 'sign_up'", false);
});

test('queued events are dropped outside production', function () {
    $this->withSession([AnalyticsEvents::SESSION_KEY => [
        ['name' => AnalyticsEvents::DEMO_START, 'parameters' => []],
    ]]);

    $this->get(route('home'))->assertOk()->assertDontSee('gtag(', false);

    expect(session()->has(AnalyticsEvents::SESSION_KEY))->toBeFalse();
});
