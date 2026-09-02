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
use Livewire\Livewire;
use Tests\Fakes\FakeMollieGateway;

beforeEach(function () {
    config()->set('services.mollie.key', 'test_platform_key');

    $this->gateway = new FakeMollieGateway;
    $this->app->instance(MollieGateway::class, $this->gateway);

    $this->club = Club::factory()->create(['platform_mollie_customer_id' => 'cst_platform']);
    $this->addon = Addon::factory()->moneybird()->create(['price_cents' => 1000]);

    $this->subscription = AddonSubscription::factory()->active()->create([
        'club_id' => $this->club->id,
        'addon_id' => $this->addon->id,
        'price_cents' => 1000,
        'next_billing_at' => today()->toDateString(),
    ]);
});

test('the charge command bills due add-on subscriptions via mandate and advances the billing date', function () {
    AddonSubscription::factory()->active()->create([
        'club_id' => Club::factory()->create(['platform_mollie_customer_id' => 'cst_other'])->id,
        'addon_id' => $this->addon->id,
        'next_billing_at' => today()->addDays(5)->toDateString(),
    ]);

    $this->artisan('addons:charge')->assertSuccessful();

    $payment = AddonPayment::query()->firstOrFail();

    expect(AddonPayment::query()->count())->toBe(1)
        ->and($payment->addon_subscription_id)->toBe($this->subscription->id)
        ->and($payment->purpose)->toBe(AddonPayment::PURPOSE_RECURRING)
        ->and($payment->amount_cents)->toBe(1000)
        ->and($this->gateway->createdPayments[0]['sequence_type'])->toBe('recurring')
        ->and($this->gateway->createdPayments[0]['mandate_id'])->toBe('mdt_fake')
        ->and($this->gateway->createdPayments[0]['customer_id'])->toBe('cst_platform')
        ->and($this->subscription->refresh()->next_billing_at->toDateString())->toBe(today()->addMonthNoOverflow()->toDateString());

    // Running again the same day does not charge twice.
    $this->artisan('addons:charge')->assertSuccessful();

    expect(AddonPayment::query()->count())->toBe(1);
});

test('a failed recurring payment marks the add-on past due and locks the feature', function () {
    $this->artisan('addons:charge');

    $payment = AddonPayment::query()->firstOrFail();
    $this->gateway->setPaymentStatus($payment->mollie_payment_id, PaymentStatus::Failed);
    $this->post(route('webhooks.mollie.platform'), ['id' => $payment->mollie_payment_id]);

    expect($this->subscription->refresh()->status)->toBe(SubscriptionStatus::PastDue)
        ->and($this->club->refresh()->hasAddon(AddonKey::Moneybird))->toBeFalse();
});

test('an owner can retry a failed payment and a paid retry reactivates the add-on', function () {
    $owner = User::factory()->create();
    $this->club->addStaff($owner, StaffRole::Owner);
    $this->subscription->update(['status' => SubscriptionStatus::PastDue]);
    $nextBilling = $this->subscription->next_billing_at;

    Livewire::actingAs($owner)
        ->test(ClubAddons::class, ['club' => $this->club])
        ->call('retryPayment', $this->subscription->id);

    $payment = AddonPayment::query()->firstOrFail();

    expect($this->subscription->refresh()->next_billing_at->toDateString())->toBe($nextBilling->toDateString());

    $this->gateway->setPaymentStatus($payment->mollie_payment_id, PaymentStatus::Paid);
    $this->post(route('webhooks.mollie.platform'), ['id' => $payment->mollie_payment_id]);

    expect($this->subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($this->club->refresh()->hasAddon(AddonKey::Moneybird))->toBeTrue();
});

test('the platform webhook rejects unknown or missing payment ids', function () {
    $this->post(route('webhooks.mollie.platform'), [])->assertStatus(400);
    $this->post(route('webhooks.mollie.platform'), ['id' => 'tr_unknown'])->assertNotFound();
});
