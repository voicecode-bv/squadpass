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
use App\Models\Promotion;
use App\Models\User;
use App\Services\Mollie\MollieGateway;
use Livewire\Livewire;
use Tests\Fakes\FakeMollieGateway;

beforeEach(function () {
    config()->set('services.mollie.key', 'test_platform_key');

    $this->gateway = new FakeMollieGateway;
    $this->app->instance(MollieGateway::class, $this->gateway);

    Promotion::query()->delete();
    Promotion::factory()->create(['discount_percent' => 25]);

    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

function makePromotionClub(int $discountPercent = 25): Club
{
    return Club::factory()->create()->forceFill([
        'promotion_discount_percent' => $discountPercent,
        'promotion_claimed_at' => now(),
    ]);
}

test('a club choosing a plan while the promotion runs pays the promotional price', function () {
    Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('subscribe', ClubPlan::Starter->value)
        ->assertRedirect('https://mollie.test/checkout/tr_fake2');

    $club = $this->club->fresh();

    expect($club->hasPromotionalPrice())->toBeTrue()
        ->and($club->promotion_discount_percent)->toBe(25)
        ->and(ClubSubscription::query()->sole()->price_cents)->toBe(7425)
        ->and($this->gateway->createdPayments[0]['amount_cents'])->toBe(7425)
        ->and(Promotion::claimedCount())->toBe(1);
});

test('the promotion has no limit on the number of clubs', function () {
    foreach (range(1, 20) as $club) {
        makePromotionClub()->save();
    }

    Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('subscribe', ClubPlan::Starter->value);

    expect($this->club->fresh()->hasPromotionalPrice())->toBeTrue()
        ->and(ClubSubscription::query()->sole()->price_cents)->toBe(7425);
});

test('a club pays the regular price while the promotion is inactive', function () {
    Promotion::query()->update(['is_active' => false]);

    Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('subscribe', ClubPlan::Starter->value);

    expect($this->club->fresh()->hasPromotionalPrice())->toBeFalse()
        ->and(ClubSubscription::query()->sole()->price_cents)->toBe(9900);
});

test('a failed first payment gives the promotional price back', function () {
    Livewire::actingAs($this->owner)
        ->test(Plan::class, ['club' => $this->club])
        ->call('subscribe', ClubPlan::Starter->value);

    $payment = ClubSubscriptionPayment::query()->sole();
    $this->gateway->setPaymentStatus($payment->mollie_payment_id, PaymentStatus::Expired);
    $this->post(route('webhooks.mollie.platform'), ['id' => $payment->mollie_payment_id])->assertNoContent();

    expect($this->club->fresh()->hasPromotionalPrice())->toBeFalse()
        ->and(Promotion::claimedCount())->toBe(0);
});

test('a club with a promotional price keeps its discount when it switches plans, even after the promotion changes', function () {
    $club = makePromotionClub(20);
    $club->save();
    $subscription = ClubSubscription::factory()->active()->plan(ClubPlan::Starter)->create(['club_id' => $club->id, 'price_cents' => 7920]);

    Promotion::query()->update(['is_active' => false, 'discount_percent' => 50]);

    app(ChangeClubPlan::class)->handle($subscription, ClubPlan::Growth);

    expect($subscription->fresh()->price_cents)->toBe(14400);
});

test('the website shows the promotion under its name with the promotional prices', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__(':name: :percent% off for good', ['name' => 'Black Friday', 'percent' => 25]))
        ->assertSee(__(':percent% off every plan, for good', ['percent' => 25]))
        ->assertSee('€ 74,25')
        ->assertSee('€ 135,00')
        ->assertSee(__('What is the :name offer?', ['name' => 'Black Friday']));
});

test('the website hides the promotion while it is inactive', function () {
    Promotion::query()->update(['is_active' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Black Friday')
        ->assertDontSee('€ 74,25')
        ->assertSee('€ 99,00');
});

test('the plan page shows the promotion to a club without a plan and the promotional price to a club that claimed it', function () {
    $this->actingAs($this->owner)
        ->get(route('clubs.plan', $this->club))
        ->assertOk()
        ->assertSee(__(':name: :percent% off for good', ['name' => 'Black Friday', 'percent' => 25]))
        ->assertSee('€ 74,25');

    $this->club->forceFill(['promotion_discount_percent' => 20, 'promotion_claimed_at' => now()])->save();

    $this->actingAs($this->owner)
        ->get(route('clubs.plan', $this->club))
        ->assertOk()
        ->assertSee(__('Your promotional price: :percent% off', ['percent' => 20]))
        ->assertSee('€ 79,20');
});

test('a superadmin changes the promotion and its name', function () {
    $superadmin = User::factory()->superadmin()->create();

    Livewire::actingAs($superadmin)
        ->test(ClubPlans::class)
        ->call('editPromotion')
        ->assertSet('promotion_name', 'Black Friday')
        ->assertSet('promotion_discount', '25')
        ->set('promotion_name', 'Cyber Monday')
        ->set('promotion_discount', '30')
        ->set('promotion_active', false)
        ->call('savePromotion')
        ->assertHasNoErrors();

    expect(Promotion::settings())->toBe(['name' => 'Cyber Monday', 'is_active' => false, 'discount_percent' => 30]);
});

test('the promotion settings are validated', function () {
    $superadmin = User::factory()->superadmin()->create();

    Livewire::actingAs($superadmin)
        ->test(ClubPlans::class)
        ->call('editPromotion')
        ->set('promotion_name', '')
        ->set('promotion_discount', '0')
        ->call('savePromotion')
        ->assertHasErrors(['promotion_name', 'promotion_discount']);

    expect(Promotion::discountPercent())->toBe(25);
});

test('a club owner cannot change the promotion', function () {
    Livewire::actingAs($this->owner)
        ->test(ClubPlans::class)
        ->assertForbidden();
});
