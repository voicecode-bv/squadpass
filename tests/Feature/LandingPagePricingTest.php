<?php

use App\Models\Addon;

test('the landing page shows a monthly price per club size', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('One price per month, based on your club size'))
        ->assertSee(__('Up to 500 members'))
        ->assertSee('€ 99,00')
        ->assertSee(__('Up to 1,000 members'))
        ->assertSee('€ 180,00')
        ->assertSee(__('More than 1,000 members'))
        ->assertSee(__('On request'));
});

test('the pricing section points to the cheapest add-on price', function () {
    Addon::factory()->mollie()->create(['price_cents' => 1500]);
    Addon::factory()->moneybird()->create(['price_cents' => 2500]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('Add-ons such as online payments, invoicing and your own mobile app are optional and billed separately, from :price per month.', ['price' => '€ 15,00']));
});

test('the pricing section omits the add-on price without add-ons for sale', function () {
    Addon::factory()->mollie()->inactive()->create(['price_cents' => 1500]);
    Addon::query()->update(['is_active' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('Add-ons such as online payments, invoicing and your own mobile app are optional and billed separately.'))
        ->assertDontSee('€ 15,00');
});
