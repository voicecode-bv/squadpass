<?php

use App\Enums\ClubPlan;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Livewire\Clubs\Plan;
use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\ClubSubscriptionPayment;
use App\Models\User;
use App\Services\Mollie\MollieGateway;
use Livewire\Livewire;
use Tests\Fakes\FakeMollieGateway;

beforeEach(function () {
    config()->set('services.mollie.key', 'test_platform_key');

    $this->gateway = new FakeMollieGateway;
    $this->app->instance(MollieGateway::class, $this->gateway);

    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

function payFirstPlanPayment($test, ClubPlan $plan = ClubPlan::Starter, PaymentStatus $status = PaymentStatus::Paid): ?ClubSubscription
{
    Livewire::actingAs($test->owner)
        ->test(Plan::class, ['club' => $test->club])
        ->call('subscribe', $plan->value);

    $payment = ClubSubscriptionPayment::query()->firstOrFail();

    $test->gateway->setPaymentStatus($payment->mollie_payment_id, $status, mandateId: 'mdt_platform');
    $test->post(route('webhooks.mollie.platform'), ['id' => $payment->mollie_payment_id])->assertNoContent();

    return ClubSubscription::query()->first();
}

test('only club owners can open the plan page', function () {
    $admin = User::factory()->create();
    $this->club->addStaff($admin, StaffRole::Admin);

    $this->actingAs($this->owner)->get(route('clubs.plan', $this->club))->assertOk()->assertSee(__('Your trial runs until :date', ['date' => $this->club->trial_ends_at->format('d-m-Y')]));
    $this->actingAs($admin)->get(route('clubs.plan', $this->club))->assertForbidden();
});

test('an owner chooses a plan and is sent to the platform Mollie checkout', function () {
    Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('subscribe', ClubPlan::Growth->value)
        ->assertRedirect('https://mollie.test/checkout/tr_fake2');

    $subscription = ClubSubscription::query()->firstOrFail();
    $payment = ClubSubscriptionPayment::query()->firstOrFail();

    expect($subscription->status)->toBe(SubscriptionStatus::Pending)
        ->and($subscription->plan)->toBe(ClubPlan::Growth)
        ->and($subscription->price_cents)->toBe(18000)
        ->and($payment->purpose)->toBe(ClubSubscriptionPayment::PURPOSE_FIRST)
        ->and($this->gateway->createdPayments[0]['account'])->toBe('platform')
        ->and($this->gateway->createdPayments[0]['sequence_type'])->toBe('first')
        ->and($this->gateway->createdPayments[0]['amount_cents'])->toBe(18000)
        ->and($this->gateway->createdPayments[0]['redirect_url'])->toBe(route('clubs.plan', $this->club));
});

test('a paid first payment activates the plan with a mandate, also after the trial', function () {
    $this->club->forceFill(['trial_ends_at' => today()->subDay()])->save();

    $subscription = payFirstPlanPayment($this);

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->mollie_mandate_id)->toBe('mdt_platform')
        ->and($subscription->next_billing_at->toDateString())->toBe(today()->addMonthNoOverflow()->toDateString())
        ->and($this->club->fresh()->canAddMembers())->toBeTrue();
});

test('a failed first payment drops the pending plan so the owner can try again', function () {
    payFirstPlanPayment($this, status: PaymentStatus::Failed);

    expect(ClubSubscription::query()->count())->toBe(0);
});

test('a club cannot take out a second plan', function () {
    ClubSubscription::factory()->active()->create(['club_id' => $this->club->id]);

    Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('subscribe', ClubPlan::Growth->value)
        ->assertNoRedirect();

    expect(ClubSubscription::query()->count())->toBe(1)
        ->and($this->gateway->createdPayments)->toBeEmpty();
});

test('the enterprise plan cannot be bought online', function () {
    Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('subscribe', ClubPlan::Enterprise->value)
        ->assertNoRedirect();

    expect(ClubSubscription::query()->count())->toBe(0);
});

test('the charge command bills due plans via mandate and skips manually billed plans', function () {
    $this->club->update(['platform_mollie_customer_id' => 'cst_platform']);
    $subscription = ClubSubscription::factory()->active()->create([
        'club_id' => $this->club->id,
        'next_billing_at' => today()->toDateString(),
    ]);
    ClubSubscription::factory()->active()->plan(ClubPlan::Enterprise)->create([
        'club_id' => Club::factory()->create(['platform_mollie_customer_id' => 'cst_other'])->id,
        'mollie_mandate_id' => null,
        'next_billing_at' => today()->toDateString(),
    ]);

    $this->artisan('club-subscriptions:charge')->assertSuccessful();

    $payment = ClubSubscriptionPayment::query()->sole();

    expect($payment->club_subscription_id)->toBe($subscription->id)
        ->and($payment->amount_cents)->toBe(9900)
        ->and($this->gateway->createdPayments[0]['sequence_type'])->toBe('recurring')
        ->and($this->gateway->createdPayments[0]['mandate_id'])->toBe('mdt_fake')
        ->and($subscription->refresh()->next_billing_at->toDateString())->toBe(today()->addMonthNoOverflow()->toDateString());

    // Running again the same day does not charge twice.
    $this->artisan('club-subscriptions:charge')->assertSuccessful();

    expect(ClubSubscriptionPayment::query()->count())->toBe(1);
});

test('a failed recurring payment puts the plan past due until a retry is paid', function () {
    $this->club->update(['platform_mollie_customer_id' => 'cst_platform']);
    $this->club->forceFill(['trial_ends_at' => today()->subDay()])->save();
    $subscription = ClubSubscription::factory()->active()->create([
        'club_id' => $this->club->id,
        'next_billing_at' => today()->toDateString(),
    ]);

    $this->artisan('club-subscriptions:charge')->assertSuccessful();
    $payment = ClubSubscriptionPayment::query()->sole();
    $this->gateway->setPaymentStatus($payment->mollie_payment_id, PaymentStatus::Failed);
    $this->post(route('webhooks.mollie.platform'), ['id' => $payment->mollie_payment_id])->assertNoContent();

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::PastDue)
        ->and($this->club->fresh()->canAddMembers())->toBeFalse();

    Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('retryPayment');

    $retry = ClubSubscriptionPayment::query()->latest('id')->firstOrFail();
    $this->gateway->setPaymentStatus($retry->mollie_payment_id, PaymentStatus::Paid);
    $this->post(route('webhooks.mollie.platform'), ['id' => $retry->mollie_payment_id])->assertNoContent();

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($this->club->fresh()->canAddMembers())->toBeTrue();
});

test('an owner can switch plans, with the new price from the next billing date', function () {
    $subscription = ClubSubscription::factory()->active()->create(['club_id' => $this->club->id]);
    $nextBillingAt = $subscription->next_billing_at->toDateString();

    Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('changePlan', ClubPlan::Growth->value);

    $subscription->refresh();

    expect($subscription->plan)->toBe(ClubPlan::Growth)
        ->and($subscription->price_cents)->toBe(18000)
        ->and($subscription->next_billing_at->toDateString())->toBe($nextBillingAt)
        ->and($this->club->fresh()->memberLimit())->toBe(1000);
});

test('an owner can cancel and resume the plan within the paid month', function () {
    $subscription = ClubSubscription::factory()->active()->create(['club_id' => $this->club->id]);
    $endOfPaidMonth = $subscription->next_billing_at->subDay()->toDateString();

    $component = Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('cancel');

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($subscription->ends_at->toDateString())->toBe($endOfPaidMonth)
        ->and($this->club->fresh()->currentSubscription()?->id)->toBe($subscription->id);

    $component->call('resume');

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->next_billing_at->toDateString())->toBe(today()->parse($endOfPaidMonth)->addDay()->toDateString());
});

test('only superadmins can activate a manually billed plan', function () {
    Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('activateManually', ClubPlan::Enterprise->value)
        ->assertForbidden();

    Livewire::actingAs(User::factory()->superadmin()->create())
        ->test(Plan::class, ['club' => $this->club])
        ->call('activateManually', ClubPlan::Enterprise->value);

    $subscription = ClubSubscription::query()->sole();

    expect($subscription->plan)->toBe(ClubPlan::Enterprise)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->isBilledManually())->toBeTrue()
        ->and($this->club->fresh()->memberLimit())->toBeNull();
});

test('a pending first payment is highlighted on the plan page', function () {
    ClubSubscription::factory()->create(['club_id' => $this->club->id, 'status' => SubscriptionStatus::Pending]);

    $this->actingAs($this->owner)
        ->get(route('clubs.plan', $this->club))
        ->assertOk()
        ->assertSee(__('Payment pending'))
        ->assertSee(__('Waiting for your first payment. Your plan is activated as soon as it is paid.'));
});
