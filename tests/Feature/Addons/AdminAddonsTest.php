<?php

use App\Enums\AddonKey;
use App\Enums\StaffRole;
use App\Livewire\Admin\Addons as AdminAddons;
use App\Models\Addon;
use App\Models\AddonSubscription;
use App\Models\Club;
use App\Models\User;
use Livewire\Livewire;

test('a superadmin sees every add-on key, created on first visit', function () {
    $superadmin = User::factory()->superadmin()->create();

    Livewire::actingAs($superadmin)
        ->test(AdminAddons::class)
        ->assertSee(AddonKey::Mollie->defaultName())
        ->assertSee(AddonKey::Moneybird->defaultName());

    expect(Addon::query()->count())->toBe(count(AddonKey::cases()))
        ->and(Addon::query()->where('key', AddonKey::Mollie->value)->first())
        ->price_cents->toBe(0)
        ->is_active->toBeFalse();
});

test('a superadmin can price and publish an add-on', function () {
    $superadmin = User::factory()->superadmin()->create();
    $addon = Addon::factory()->mollie()->inactive()->create(['price_cents' => 0]);

    Livewire::actingAs($superadmin)
        ->test(AdminAddons::class)
        ->call('edit', $addon->id)
        ->assertSet('name', $addon->name)
        ->set('name', 'Online betalen')
        ->set('description', 'Mollie-koppeling voor leden.')
        ->set('price', '12.50')
        ->set('is_active', true)
        ->call('save')
        ->assertHasNoErrors();

    $addon->refresh();

    expect($addon->name)->toBe('Online betalen')
        ->and($addon->price_cents)->toBe(1250)
        ->and($addon->is_active)->toBeTrue()
        ->and($addon->key)->toBe(AddonKey::Mollie);
});

test('a superadmin sees club add-on subscriptions', function () {
    $superadmin = User::factory()->superadmin()->create();
    $club = Club::factory()->create(['name' => 'Club Zeta']);
    AddonSubscription::factory()->active()->create([
        'club_id' => $club->id,
        'addon_id' => Addon::factory()->mollie()->create()->id,
    ]);

    Livewire::actingAs($superadmin)
        ->test(AdminAddons::class)
        ->assertSee('Club Zeta');
});

test('a club owner cannot open the add-on management page', function () {
    $owner = User::factory()->create();
    Club::factory()->create()->addStaff($owner, StaffRole::Owner);

    Livewire::actingAs($owner)
        ->test(AdminAddons::class)
        ->assertForbidden();

    $this->actingAs($owner)->get(route('admin.addons'))->assertForbidden();
});
