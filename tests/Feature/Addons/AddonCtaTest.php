<?php

use App\Enums\AddonKey;
use App\Enums\StaffRole;
use App\Livewire\Clubs\AddonCta;
use App\Models\Addon;
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

    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);

    $this->addon = Addon::factory()->moneybird()->create(['price_cents' => 1500]);
});

test('owners see the pitch, price and can buy the add-on straight from the call to action', function () {
    Livewire::actingAs($this->owner)
        ->test(AddonCta::class, ['club' => $this->club, 'addonKey' => AddonKey::Moneybird])
        ->assertSee(AddonKey::Moneybird->pitch())
        ->assertSee($this->addon->formattedPrice())
        ->assertSee(__('Activate :addon', ['addon' => $this->addon->name]))
        ->call('purchase')
        ->assertRedirect('https://mollie.test/checkout/tr_fake2');

    expect(AddonSubscription::query()->sole()->addon_id)->toBe($this->addon->id);
});

test('club admins are asked to involve the owner and cannot buy the add-on', function () {
    $admin = User::factory()->create();
    $this->club->addStaff($admin, StaffRole::Admin);

    Livewire::actingAs($admin)
        ->test(AddonCta::class, ['club' => $this->club, 'addonKey' => AddonKey::Moneybird])
        ->assertSee(__('Ask the owner of your club to activate this add-on.'))
        ->assertDontSee(__('Activate :addon', ['addon' => $this->addon->name]))
        ->call('purchase')
        ->assertForbidden();

    expect(AddonSubscription::query()->exists())->toBeFalse();
});

test('an add-on that is not for sale cannot be bought from the call to action', function () {
    $this->addon->update(['is_active' => false]);

    Livewire::actingAs($this->owner)
        ->test(AddonCta::class, ['club' => $this->club, 'addonKey' => AddonKey::Moneybird])
        ->assertSee(__('This add-on is not available right now. Keep an eye on this page.'))
        ->call('purchase')
        ->assertNotFound();
});

test('the invoices page shows the call to action instead of invoices without the Moneybird add-on', function () {
    $this->actingAs($this->owner)
        ->get(route('clubs.invoices', $this->club))
        ->assertOk()
        ->assertSeeLivewire(AddonCta::class)
        ->assertDontSee(__('Sync with Moneybird'));
});

test('the invoices page asks to connect when the add-on is active but Moneybird is not connected', function () {
    $club = Club::factory()->withAddon(AddonKey::Moneybird)->create();
    $club->addStaff($this->owner, StaffRole::Owner);

    $this->actingAs($this->owner)
        ->get(route('clubs.invoices', $club))
        ->assertOk()
        ->assertDontSeeLivewire(AddonCta::class)
        ->assertSee(__('Connect your Moneybird administration'));
});
