<?php

use App\Enums\AddonKey;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Livewire\Clubs\Addons as ClubAddons;
use App\Models\Addon;
use App\Models\AddonPayment;
use App\Models\AddonSubscription;
use App\Models\Club;
use App\Models\User;
use App\Services\Mollie\MollieGateway;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\Fakes\FakeMollieGateway;

beforeEach(function () {
    config()->set('services.mollie.key', 'test_platform_key');

    $this->gateway = new FakeMollieGateway;
    $this->app->instance(MollieGateway::class, $this->gateway);

    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);

    $this->addon = Addon::factory()->mollie()->create(['price_cents' => 1500]);
});

function payFirstAddonPayment($test, PaymentStatus $status = PaymentStatus::Paid): AddonSubscription
{
    Livewire::actingAs($test->owner)
        ->test(ClubAddons::class, ['club' => $test->club])
        ->call('purchase', $test->addon->id);

    $payment = AddonPayment::query()->firstOrFail();

    $test->gateway->setPaymentStatus($payment->mollie_payment_id, $status, mandateId: 'mdt_platform');
    $test->post(route('webhooks.mollie.platform'), ['id' => $payment->mollie_payment_id])->assertNoContent();

    return AddonSubscription::query()->firstOrFail();
}

test('an owner can buy an add-on and is redirected to the platform Mollie checkout', function () {
    Livewire::actingAs($this->owner)
        ->test(ClubAddons::class, ['club' => $this->club])
        ->call('purchase', $this->addon->id)
        ->assertRedirect('https://mollie.test/checkout/tr_fake2');

    $subscription = AddonSubscription::query()->firstOrFail();
    $payment = AddonPayment::query()->firstOrFail();

    expect($subscription->status)->toBe(SubscriptionStatus::Pending)
        ->and($subscription->price_cents)->toBe(1500)
        ->and($payment->purpose)->toBe(AddonPayment::PURPOSE_FIRST)
        ->and($this->gateway->createdCustomers[0]['account'])->toBe('platform')
        ->and($this->gateway->createdPayments[0]['account'])->toBe('platform')
        ->and($this->gateway->createdPayments[0]['sequence_type'])->toBe('first')
        ->and($this->gateway->createdPayments[0]['webhook_url'])->toBe(route('webhooks.mollie.platform'))
        ->and($this->club->refresh()->platform_mollie_customer_id)->toBe('cst_fake1')
        ->and($this->club->hasAddon(AddonKey::Mollie))->toBeFalse();
});

test('a paid first payment activates the add-on with a mandate and next billing date', function () {
    $subscription = payFirstAddonPayment($this);

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->mollie_mandate_id)->toBe('mdt_platform')
        ->and($subscription->next_billing_at->toDateString())->toBe(today()->addMonthNoOverflow()->toDateString())
        ->and($this->club->refresh()->hasAddon(AddonKey::Mollie))->toBeTrue();

    // A replayed webhook changes nothing.
    $payment = AddonPayment::query()->firstOrFail();
    $this->post(route('webhooks.mollie.platform'), ['id' => $payment->mollie_payment_id])->assertNoContent();

    expect(AddonSubscription::query()->count())->toBe(1)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Paid);
});

test('a failed first payment removes the pending subscription so the club can retry', function () {
    payFirstAddonPayment($this, PaymentStatus::Failed);
})->throws(ModelNotFoundException::class);

test('a club cannot buy an add-on it already has', function () {
    payFirstAddonPayment($this);

    Livewire::actingAs($this->owner)
        ->test(ClubAddons::class, ['club' => $this->club])
        ->call('purchase', $this->addon->id)
        ->assertNoRedirect();

    expect(AddonSubscription::query()->count())->toBe(1);
});

test('an add-on that is not for sale cannot be bought', function () {
    $this->addon->update(['is_active' => false]);

    Livewire::actingAs($this->owner)
        ->test(ClubAddons::class, ['club' => $this->club])
        ->call('purchase', $this->addon->id);

    expect(AddonSubscription::query()->count())->toBe(0);
})->throws(ModelNotFoundException::class);

test('buying fails gracefully when the platform Mollie key is missing', function () {
    config()->set('services.mollie.key', null);

    Livewire::actingAs($this->owner)
        ->test(ClubAddons::class, ['club' => $this->club])
        ->call('purchase', $this->addon->id)
        ->assertNoRedirect();

    expect(AddonSubscription::query()->count())->toBe(0);
});

test('a club admin who is not an owner cannot manage add-ons', function () {
    $admin = User::factory()->create();
    $this->club->addStaff($admin, StaffRole::Admin);

    Livewire::actingAs($admin)
        ->test(ClubAddons::class, ['club' => $this->club])
        ->assertForbidden();
});

test('an owner cannot cancel another clubs add-on subscription', function () {
    $foreign = AddonSubscription::factory()->active()->create(['addon_id' => $this->addon->id]);

    Livewire::actingAs($this->owner)
        ->test(ClubAddons::class, ['club' => $this->club])
        ->call('cancel', $foreign->id);
})->throws(ModelNotFoundException::class);

test('cancelling keeps the add-on unlocked until the paid month ends and stops billing', function () {
    $subscription = payFirstAddonPayment($this);

    Livewire::actingAs($this->owner)
        ->test(ClubAddons::class, ['club' => $this->club])
        ->call('cancel', $subscription->id);

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($subscription->next_billing_at)->toBeNull()
        ->and($subscription->ends_at->toDateString())->toBe(today()->addMonthNoOverflow()->subDay()->toDateString())
        ->and($this->club->refresh()->hasAddon(AddonKey::Mollie))->toBeTrue();

    $this->travelTo($subscription->ends_at->addDay());

    expect(Club::query()->findOrFail($this->club->id)->hasAddon(AddonKey::Mollie))->toBeFalse();
});

test('a cancelled add-on can be resumed while it is still unlocked', function () {
    $subscription = payFirstAddonPayment($this);
    $nextBilling = $subscription->next_billing_at;

    Livewire::actingAs($this->owner)
        ->test(ClubAddons::class, ['club' => $this->club])
        ->call('cancel', $subscription->id)
        ->call('resume', $subscription->id);

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->ends_at)->toBeNull()
        ->and($subscription->cancelled_at)->toBeNull()
        ->and($subscription->next_billing_at->toDateString())->toBe($nextBilling->toDateString());
});
