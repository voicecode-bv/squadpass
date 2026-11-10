<?php

use App\Actions\ClubSubscriptions\ChangeClubPlan;
use App\Enums\ClubPlan;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Livewire\Admin\ClubPlans;
use App\Livewire\Clubs\Plan;
use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\ClubSubscriptionPayment;
use App\Models\LaunchOffer;
use App\Models\User;
use App\Services\Mollie\MollieGateway;
use Livewire\Livewire;
use Tests\Fakes\FakeMollieGateway;

beforeEach(function () {
    config()->set('services.mollie.key', 'test_platform_key');

    $this->gateway = new FakeMollieGateway;
    $this->app->instance(MollieGateway::class, $this->gateway);

    LaunchOffer::query()->delete();
    LaunchOffer::factory()->create(['discount_percent' => 25, 'spots' => 2]);

    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

function makeFoundingClub(int $discountPercent = 25): Club
{
    return Club::factory()->create()->forceFill([
        'founding_discount_percent' => $discountPercent,
        'founding_claimed_at' => now(),
    ]);
}

test('a club choosing a plan while spots are left claims a founding spot and pays the opening price', function () {
    Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('subscribe', ClubPlan::Starter->value)
        ->assertRedirect('https://mollie.test/checkout/tr_fake2');

    $club = $this->club->fresh();

    expect($club->isFoundingMember())->toBeTrue()
        ->and($club->founding_discount_percent)->toBe(25)
        ->and(ClubSubscription::query()->sole()->price_cents)->toBe(7425)
        ->and($this->gateway->createdPayments[0]['amount_cents'])->toBe(7425)
        ->and(LaunchOffer::spotsLeft())->toBe(1);
});

test('a club pays the regular price once every founding spot is taken', function () {
    makeFoundingClub()->save();
    makeFoundingClub()->save();

    Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('subscribe', ClubPlan::Starter->value);

    expect($this->club->fresh()->isFoundingMember())->toBeFalse()
        ->and(ClubSubscription::query()->sole()->price_cents)->toBe(9900);
});

test('a club pays the regular price while the offer is inactive', function () {
    LaunchOffer::query()->update(['is_active' => false]);

    Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('subscribe', ClubPlan::Starter->value);

    expect($this->club->fresh()->isFoundingMember())->toBeFalse()
        ->and(ClubSubscription::query()->sole()->price_cents)->toBe(9900);
});

test('a failed first payment gives the founding spot back', function () {
    Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('subscribe', ClubPlan::Starter->value);

    $payment = ClubSubscriptionPayment::query()->sole();
    $this->gateway->setPaymentStatus($payment->mollie_payment_id, PaymentStatus::Expired);
    $this->post(route('webhooks.mollie.platform'), ['id' => $payment->mollie_payment_id])->assertNoContent();

    expect($this->club->fresh()->isFoundingMember())->toBeFalse()
        ->and(LaunchOffer::spotsLeft())->toBe(2);
});

test('a founding club keeps its discount when it switches plans, even after the offer changes', function () {
    $club = makeFoundingClub(20);
    $club->save();
    $subscription = ClubSubscription::factory()->active()->plan(ClubPlan::Starter)->create(['club_id' => $club->id, 'price_cents' => 7920]);

    LaunchOffer::query()->update(['is_active' => false, 'discount_percent' => 50]);

    app(ChangeClubPlan::class)->handle($subscription, ClubPlan::Growth);

    expect($subscription->fresh()->price_cents)->toBe(14400);
});

test('the website shows the opening offer with the spots left and the opening prices', function () {
    makeFoundingClub()->save();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('Opening offer: :percent% off for good, :count left', ['percent' => 25, 'count' => trans_choice(':count spot|:count spots', 1)]))
        ->assertSee(__(':percent% off for good for the first :spots clubs', ['percent' => 25, 'spots' => 2]))
        ->assertSee('€ 74,25')
        ->assertSee('€ 135,00')
        ->assertSee(__('What is the opening offer?'));
});

test('the website hides the opening offer once every spot is taken', function () {
    makeFoundingClub()->save();
    makeFoundingClub()->save();

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee(__('Opening offer'))
        ->assertDontSee('€ 74,25')
        ->assertSee('€ 99,00');
});

test('the plan page shows the opening offer to a club without a plan and the opening price to a club that claimed it', function () {
    $this->actingAs($this->owner)
        ->get(route('clubs.plan', $this->club))
        ->assertOk()
        ->assertSee(__('Opening offer: :percent% off for good', ['percent' => 25]))
        ->assertSee('€ 74,25');

    $this->club->forceFill(['founding_discount_percent' => 20, 'founding_claimed_at' => now()])->save();

    $this->actingAs($this->owner)
        ->get(route('clubs.plan', $this->club))
        ->assertOk()
        ->assertSee(__('Opening price: :percent% off', ['percent' => 20]))
        ->assertSee('€ 79,20');
});

test('a superadmin changes the opening offer', function () {
    $superadmin = User::factory()->superadmin()->create();

    Livewire::actingAs($superadmin)
        ->test(ClubPlans::class)
        ->call('editOffer')
        ->assertSet('offer_discount_percent', '25')
        ->assertSet('offer_spots', '2')
        ->set('offer_discount_percent', '30')
        ->set('offer_spots', '15')
        ->set('offer_active', false)
        ->call('saveOffer')
        ->assertHasNoErrors();

    expect(LaunchOffer::settings())->toBe(['is_active' => false, 'discount_percent' => 30, 'spots' => 15]);
});

test('the opening offer settings are validated', function () {
    $superadmin = User::factory()->superadmin()->create();

    Livewire::actingAs($superadmin)
        ->test(ClubPlans::class)
        ->call('editOffer')
        ->set('offer_discount_percent', '0')
        ->set('offer_spots', '-1')
        ->call('saveOffer')
        ->assertHasErrors(['offer_discount_percent', 'offer_spots']);

    expect(LaunchOffer::discountPercent())->toBe(25);
});

test('a club owner cannot change the opening offer', function () {
    Livewire::actingAs($this->owner)
        ->test(ClubPlans::class)
        ->assertForbidden();
});
