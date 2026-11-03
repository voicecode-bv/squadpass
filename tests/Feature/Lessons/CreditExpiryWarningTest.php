<?php

use App\Models\CreditGrant;
use App\Models\Subscription;
use App\Notifications\CreditsExpiring;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->freezeTime();
});

test('a member gets one warning for all credits expiring within three days', function () {
    Notification::fake();
    $subscription = Subscription::factory()->active()->create();
    CreditGrant::factory()->for($subscription)->create(['amount' => 2, 'expires_at' => now()->addDays(1)]);
    CreditGrant::factory()->for($subscription)->create(['amount' => 3, 'expires_at' => now()->addDays(2)]);

    $this->artisan('credits:warn-expiring')->assertSuccessful();
    $this->artisan('credits:warn-expiring')->assertSuccessful();

    Notification::assertSentToTimes($subscription->user, CreditsExpiring::class, 1);
    Notification::assertSentTo($subscription->user, CreditsExpiring::class, fn (CreditsExpiring $notification) => $notification->credits === 5
        && $notification->expiresAt->toDateTimeString() === now()->addDay()->toDateTimeString());
});

test('used up, later expiring and already expired credits get no warning', function () {
    Notification::fake();
    $subscription = Subscription::factory()->active()->create();
    CreditGrant::factory()->for($subscription)->create(['amount' => 2, 'remaining' => 0, 'expires_at' => now()->addDay()]);
    CreditGrant::factory()->for($subscription)->create(['expires_at' => now()->addDays(5)]);
    CreditGrant::factory()->for($subscription)->expired()->create();

    $this->artisan('credits:warn-expiring')->assertSuccessful();

    Notification::assertNothingSent();
});
