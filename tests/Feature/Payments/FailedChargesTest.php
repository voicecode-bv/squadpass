<?php

use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Livewire\Clubs\Dashboard;
use App\Livewire\Subscriptions\Index;
use App\Models\Club;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);

    $this->subscription = Subscription::factory()->for($this->club)->create(['status' => SubscriptionStatus::PastDue]);
});

function failedRecurringPayment(Club $club, Subscription $subscription, array $attributes = []): Payment
{
    return Payment::factory()->for($club)->create([
        'user_id' => $subscription->user_id,
        'payable_type' => Subscription::class,
        'payable_id' => $subscription->id,
        'status' => PaymentStatus::Failed,
        'metadata' => ['purpose' => 'subscription_recurring'],
        ...$attributes,
    ]);
}

test('staff see failed recurring charges on the subscriptions page', function () {
    $this->subscription->user->update(['name' => 'Wanbetalend Lid']);
    failedRecurringPayment($this->club, $this->subscription, ['amount_cents' => 5900]);

    Livewire::actingAs($this->owner)
        ->test(Index::class, ['club' => $this->club])
        ->assertSee(__('Failed charges'))
        ->assertSee('Wanbetalend Lid')
        ->assertSee('59,00');
});

test('paid and non-recurring payments are not listed as failed charges', function () {
    Payment::factory()->for($this->club)->paid()->create(['metadata' => ['purpose' => 'subscription_recurring']]);
    Payment::factory()->for($this->club)->create(['status' => PaymentStatus::Failed, 'metadata' => ['purpose' => 'strip_card']]);

    $component = Livewire::actingAs($this->owner)->test(Index::class, ['club' => $this->club]);

    expect($component->instance()->failedCharges)->toHaveCount(0);
});

test('the club dashboard warns about failed charges of the last 30 days', function () {
    failedRecurringPayment($this->club, $this->subscription);

    $oldSubscription = Subscription::factory()->for($this->club)->create(['status' => SubscriptionStatus::PastDue]);
    failedRecurringPayment($this->club, $oldSubscription, [
        'created_at' => now()->subDays(45),
        'updated_at' => now()->subDays(45),
    ]);

    $component = Livewire::actingAs($this->owner)->test(Dashboard::class, ['club' => $this->club]);

    expect($component->instance()->failedChargeCount)->toBe(1);
});
