<?php

use App\Enums\PaymentStatus;
use App\Livewire\Portal\BuyStripCard;
use App\Models\Club;
use App\Models\MollieCustomer;
use App\Models\Payment;
use App\Models\StripCard;
use App\Models\StripCardPlan;
use App\Models\User;
use App\Services\Mollie\MollieGateway;
use Livewire\Livewire;
use Tests\Fakes\FakeMollieGateway;

beforeEach(function () {
    $this->gateway = new FakeMollieGateway;
    $this->app->instance(MollieGateway::class, $this->gateway);

    $this->club = Club::factory()->withMollieKey()->create();
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);

    $this->plan = StripCardPlan::factory()->for($this->club)->create([
        'strips' => 10,
        'price_cents' => 7500,
        'validity_months' => 12,
    ]);
});

test('a member is redirected to mollie checkout when buying a strip card', function () {
    Livewire::actingAs($this->member)
        ->test(BuyStripCard::class)
        ->call('buy', $this->plan->id)
        ->assertRedirect('https://mollie.test/checkout/tr_fake2');

    $payment = Payment::query()->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Open)
        ->and($payment->amount_cents)->toBe(7500)
        ->and($payment->purpose())->toBe('strip_card')
        ->and(StripCard::query()->count())->toBe(0)
        ->and(MollieCustomer::query()->count())->toBe(1);
});

test('a paid webhook creates the strip card exactly once', function () {
    Livewire::actingAs($this->member)
        ->test(BuyStripCard::class)
        ->call('buy', $this->plan->id);

    $payment = Payment::query()->firstOrFail();

    $this->gateway->setPaymentStatus($payment->mollie_payment_id, PaymentStatus::Paid);

    $this->post(route('webhooks.mollie', $this->club), ['id' => $payment->mollie_payment_id])
        ->assertNoContent();

    $card = StripCard::query()->firstOrFail();

    expect($card->strips_total)->toBe(10)
        ->and($card->strips_remaining)->toBe(10)
        ->and($card->user_id)->toBe($this->member->id)
        ->and($card->expires_at->toDateString())->toBe(today()->addMonths(12)->toDateString())
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at)->not->toBeNull();

    // A duplicate webhook does not create a second card.
    $this->post(route('webhooks.mollie', $this->club), ['id' => $payment->mollie_payment_id])
        ->assertNoContent();

    expect(StripCard::query()->count())->toBe(1);
});

test('a failed payment creates no strip card', function () {
    Livewire::actingAs($this->member)
        ->test(BuyStripCard::class)
        ->call('buy', $this->plan->id);

    $payment = Payment::query()->firstOrFail();

    $this->gateway->setPaymentStatus($payment->mollie_payment_id, PaymentStatus::Failed);

    $this->post(route('webhooks.mollie', $this->club), ['id' => $payment->mollie_payment_id])
        ->assertNoContent();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and(StripCard::query()->count())->toBe(0);
});

test('a webhook for an unknown payment returns 404', function () {
    $this->post(route('webhooks.mollie', $this->club), ['id' => 'tr_unknown'])
        ->assertNotFound();
});

test('a webhook without id returns 400', function () {
    $this->post(route('webhooks.mollie', $this->club))
        ->assertStatus(400);
});

test('a webhook for another club cannot touch this payment', function () {
    Livewire::actingAs($this->member)
        ->test(BuyStripCard::class)
        ->call('buy', $this->plan->id);

    $payment = Payment::query()->firstOrFail();
    $otherClub = Club::factory()->withMollieKey()->create();

    $this->post(route('webhooks.mollie', $otherClub), ['id' => $payment->mollie_payment_id])
        ->assertNotFound();
});

test('buying requires an active membership', function () {
    $stranger = User::factory()->create();

    Livewire::actingAs($stranger)
        ->test(BuyStripCard::class)
        ->call('buy', $this->plan->id);

    expect(Payment::query()->count())->toBe(0);
});

test('buying is unavailable when the club has no mollie key', function () {
    $this->club->update(['mollie_api_key' => null]);

    Livewire::actingAs($this->member)
        ->test(BuyStripCard::class)
        ->call('buy', $this->plan->id);

    expect(Payment::query()->count())->toBe(0);
});

test('the mollie customer is reused for repeat purchases', function () {
    Livewire::actingAs($this->member)
        ->test(BuyStripCard::class)
        ->call('buy', $this->plan->id);

    Livewire::actingAs($this->member)
        ->test(BuyStripCard::class)
        ->call('buy', $this->plan->id);

    expect(MollieCustomer::query()->count())->toBe(1)
        ->and(count($this->gateway->createdCustomers))->toBe(1)
        ->and(Payment::query()->count())->toBe(2);
});

test('a strip card of a club without online payments is offered without a buy button', function () {
    $otherClub = Club::factory()->create();
    $otherClub->addMember($this->member);
    $otherPlan = StripCardPlan::factory()->for($otherClub)->create(['name' => 'Offline kaart']);

    Livewire::actingAs($this->member)
        ->test(BuyStripCard::class)
        ->assertSee('Offline kaart')
        ->assertSee(__('This club has not set up online payments yet. Contact the club to buy a strip card.'))
        ->assertDontSeeHtml('wire:click="buy('.$otherPlan->id.')"')
        ->assertSeeHtml('wire:click="buy('.$this->plan->id.')"');
});
