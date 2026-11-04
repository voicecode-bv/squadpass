<?php

use App\Enums\AddonKey;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Livewire\Admin\Addons as AdminAddons;
use App\Livewire\Clubs\Addons as ClubAddons;
use App\Models\Addon;
use App\Models\AddonPayment;
use App\Models\AddonSubscription;
use App\Models\Club;
use App\Models\User;
use App\Services\Mollie\MollieGateway;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Fakes\FakeMollieGateway;

beforeEach(function () {
    config()->set('services.mollie.key', 'test_platform_key');

    $this->gateway = new FakeMollieGateway;
    $this->app->instance(MollieGateway::class, $this->gateway);

    $this->travelTo(Carbon::parse('2026-11-11 10:00'));

    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);

    $this->mollie = Addon::factory()->mollie()->create(['price_cents' => 1500]);
    $this->moneybird = Addon::factory()->moneybird()->create(['price_cents' => 1000]);
    $this->bundle = Addon::factory()->bundleOf($this->mollie, $this->moneybird)->create(['price_cents' => 2200]);
});

function subscribeClubTo($test, Addon $addon, string $nextBillingAt): AddonSubscription
{
    return AddonSubscription::factory()->active()->create([
        'club_id' => $test->club->id,
        'addon_id' => $addon->id,
        'price_cents' => $addon->price_cents,
        'next_billing_at' => $nextBillingAt,
    ]);
}

function buyBundle($test, PaymentStatus $status = PaymentStatus::Paid): AddonPayment
{
    Livewire::actingAs($test->owner)
        ->test(ClubAddons::class, ['club' => $test->club])
        ->call('purchase', $test->bundle->id);

    $payment = AddonPayment::query()->latest('id')->firstOrFail();

    $test->gateway->setPaymentStatus($payment->mollie_payment_id, $status, mandateId: 'mdt_bundle');
    $test->post(route('webhooks.mollie.platform'), ['id' => $payment->mollie_payment_id])->assertNoContent();

    return $payment->fresh() ?? $payment;
}

test('a bundle subscription unlocks exactly the add-ons it includes', function () {
    AddonSubscription::factory()->active()->create(['club_id' => $this->club->id, 'addon_id' => $this->bundle->id]);

    expect($this->club->hasAddon(AddonKey::Mollie))->toBeTrue()
        ->and($this->club->hasAddon(AddonKey::Moneybird))->toBeTrue()
        ->and($this->club->hasAddon(AddonKey::MobileApp))->toBeFalse();
});

test('a club without add-ons pays the full bundle price', function () {
    $payment = buyBundle($this);

    expect($payment->amount_cents)->toBe(2200)
        ->and($payment->credit_cents)->toBe(0)
        ->and($this->club->refresh()->hasAddon(AddonKey::Moneybird))->toBeTrue();
});

test('upgrading to a bundle deducts the unused days of the replaced add-ons and stops them', function () {
    // Paid 21 Oct - 20 Nov: 10 of 31 days left. Paid 1 Nov - 30 Nov: 20 of 30 days left.
    $mollie = subscribeClubTo($this, $this->mollie, '2026-11-21');
    $moneybird = subscribeClubTo($this, $this->moneybird, '2026-12-01');

    $payment = buyBundle($this);

    // 1500 * 10/31 = 484 and 1000 * 20/30 = 667.
    expect($payment->credit_cents)->toBe(1151)
        ->and($payment->amount_cents)->toBe(2200 - 1151)
        ->and(AddonSubscription::query()->where('addon_id', $this->bundle->id)->sole())
        ->status->toBe(SubscriptionStatus::Active)
        ->price_cents->toBe(2200);

    foreach ([$mollie->refresh(), $moneybird->refresh()] as $replaced) {
        expect($replaced->status)->toBe(SubscriptionStatus::Cancelled)
            ->and($replaced->next_billing_at)->toBeNull()
            ->and($replaced->isCurrent())->toBeFalse();
    }

    $club = Club::query()->findOrFail($this->club->id);

    expect($club->hasAddon(AddonKey::Mollie))->toBeTrue()
        ->and($club->hasAddon(AddonKey::Moneybird))->toBeTrue();
});

test('the upgrade credit never lowers the first payment below the Mollie minimum', function () {
    $this->bundle->update(['price_cents' => 500]);
    subscribeClubTo($this, $this->mollie, '2026-12-10');

    $payment = buyBundle($this);

    expect($payment->amount_cents)->toBe(1)
        ->and($payment->credit_cents)->toBe(499);
});

test('replaced add-ons keep running when the bundle payment fails', function () {
    $mollie = subscribeClubTo($this, $this->mollie, '2026-11-21');

    buyBundle($this, PaymentStatus::Failed);

    expect($mollie->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($mollie->next_billing_at->toDateString())->toBe('2026-11-21');
});

test('an add-on the club already has through its bundle cannot be bought separately', function () {
    AddonSubscription::factory()->active()->create(['club_id' => $this->club->id, 'addon_id' => $this->bundle->id]);

    Livewire::actingAs($this->owner)
        ->test(ClubAddons::class, ['club' => $this->club])
        ->assertSee(__('Included in :bundle', ['bundle' => $this->bundle->name]))
        ->call('purchase', $this->mollie->id)
        ->assertNoRedirect();

    expect(AddonSubscription::query()->where('addon_id', $this->mollie->id)->exists())->toBeFalse();
});

test('a bundle that overlaps only partly with a current add-on is a normal purchase', function () {
    $mobileApp = Addon::query()->where('key', AddonKey::MobileApp->value)->firstOrFail();
    $mobileBundle = Addon::factory()->bundleOf($this->moneybird, $mobileApp)->create(['price_cents' => 8000]);
    $mollie = subscribeClubTo($this, $this->mollie, '2026-11-21');

    Livewire::actingAs($this->owner)
        ->test(ClubAddons::class, ['club' => $this->club])
        ->call('purchase', $mobileBundle->id)
        ->assertRedirect();

    expect(AddonPayment::query()->sole())
        ->amount_cents->toBe(8000)
        ->credit_cents->toBe(0)
        ->and($mollie->refresh()->status)->toBe(SubscriptionStatus::Active);
});

test('the add-ons page offers the upgrade with the lowered first payment', function () {
    subscribeClubTo($this, $this->mollie, '2026-11-21');

    Livewire::actingAs($this->owner)
        ->test(ClubAddons::class, ['club' => $this->club])
        ->assertSee(__('Upgrade'))
        ->assertSee('€ 17,16')
        ->assertSee(__('Per month, :amount less than separately', ['amount' => '€ 3,00']));
});

test('a superadmin can compose a bundle of single add-ons', function () {
    Livewire::actingAs(User::factory()->superadmin()->create())
        ->test(AdminAddons::class)
        ->call('createBundle')
        ->set('name', 'Alles-in-één')
        ->set('price', '25.00')
        ->set('is_active', true)
        ->set('includedAddonIds', [(string) $this->mollie->id, (string) $this->moneybird->id])
        ->call('save')
        ->assertHasNoErrors();

    $bundle = Addon::query()->where('name', 'Alles-in-één')->sole();

    expect($bundle->isBundle())->toBeTrue()
        ->and($bundle->price_cents)->toBe(2500)
        ->and($bundle->includedAddons->pluck('id')->all())->toBe([$this->mollie->id, $this->moneybird->id]);
});

test('a bundle needs at least two single add-ons', function (Closure $includedIds, string $errorKey) {
    Livewire::actingAs(User::factory()->superadmin()->create())
        ->test(AdminAddons::class)
        ->call('createBundle')
        ->set('name', 'Te klein')
        ->set('price', '10')
        ->set('includedAddonIds', $includedIds($this))
        ->call('save')
        ->assertHasErrors($errorKey);
})->with([
    'one add-on' => [fn ($test) => [(string) $test->mollie->id], 'includedAddonIds'],
    'a bundle inside a bundle' => [fn ($test) => [(string) $test->mollie->id, (string) $test->bundle->id], 'includedAddonIds.1'],
]);

test('only a superadmin can create bundles', function () {
    Livewire::actingAs($this->owner)
        ->test(AdminAddons::class)
        ->assertForbidden();
});

test('the landing page shows bundles for sale with their saving', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee($this->bundle->name)
        ->assertSee('€ 22,00')
        ->assertSee(__('instead of :price separately', ['price' => '€ 25,00']), escape: false);
});
