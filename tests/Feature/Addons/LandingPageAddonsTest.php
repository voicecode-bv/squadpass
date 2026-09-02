<?php

use App\Livewire\Admin\Addons as AdminAddons;
use App\Models\Addon;
use App\Models\User;
use Livewire\Livewire;

test('the landing page highlights add-ons for sale with their price', function () {
    Addon::factory()->mollie()->create(['price_cents' => 1500]);
    Addon::factory()->moneybird()->inactive()->create(['price_cents' => 1000]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('Online payments with Mollie'))
        ->assertSee('images/logos/mollie.svg')
        ->assertSee('images/logos/moneybird.svg')
        ->assertSee('€ 15,00')
        ->assertSee(__('Invoicing with Moneybird'))
        ->assertSee(__('Coming soon'))
        ->assertDontSee('€ 10,00');
});

test('changing an add-on price in the admin refreshes the landing page', function () {
    $addon = Addon::factory()->mollie()->create(['price_cents' => 1500]);

    $this->get(route('home'))->assertSee('€ 15,00');

    Livewire::actingAs(User::factory()->superadmin()->create())
        ->test(AdminAddons::class)
        ->call('edit', $addon->id)
        ->set('price', '19.95')
        ->call('save')
        ->assertHasNoErrors();

    $this->get(route('home'))->assertSee('€ 19,95')->assertDontSee('€ 15,00');
});
