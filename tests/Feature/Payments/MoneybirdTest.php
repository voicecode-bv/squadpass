<?php

use App\Actions\Moneybird\CreateInvoiceForPayment;
use App\Actions\Payments\ApplyPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Jobs\CreateMoneybirdInvoice;
use App\Livewire\Members\Show;
use App\Models\Club;
use App\Models\Payment;
use App\Models\User;
use App\Services\Mollie\MolliePaymentResult;
use App\Services\Moneybird\MoneybirdGateway;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Fakes\FakeMoneybirdGateway;

beforeEach(function () {
    $this->gateway = new FakeMoneybirdGateway;
    $this->app->instance(MoneybirdGateway::class, $this->gateway);

    $this->club = Club::factory()->create([
        'moneybird_access_token' => 'mb_token',
        'moneybird_administration_id' => '123456',
    ]);
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);

    $this->member = User::factory()->create(['name' => 'Mo Neybird']);
    $this->membership = $this->club->addMember($this->member);
});

test('staff can sync a member as Moneybird contact', function () {
    Livewire::actingAs($this->owner)
        ->test(Show::class, ['club' => $this->club, 'membership' => $this->membership])
        ->call('syncMoneybird');

    expect($this->membership->refresh()->moneybird_contact_id)->toBe('mb_contact_1')
        ->and($this->gateway->upsertedContacts)->toHaveCount(1)
        ->and($this->gateway->upsertedContacts[0]['attributes']['firstname'])->toBe('Mo')
        ->and($this->gateway->upsertedContacts[0]['attributes']['customer_id'])->toBe((string) $this->membership->membership_number);
});

test('syncing without Moneybird configuration does nothing', function () {
    $this->club->update(['moneybird_access_token' => null, 'moneybird_administration_id' => null]);

    Livewire::actingAs($this->owner)
        ->test(Show::class, ['club' => $this->club, 'membership' => $this->membership])
        ->call('syncMoneybird');

    expect($this->membership->refresh()->moneybird_contact_id)->toBeNull()
        ->and($this->gateway->upsertedContacts)->toHaveCount(0);
});

test('a paid payment creates a draft invoice and syncs the contact once', function () {
    $payment = Payment::factory()->for($this->club)->paid()->create([
        'user_id' => $this->member->id,
        'amount_cents' => 7500,
        'metadata' => ['purpose' => 'strip_card'],
    ]);

    $action = app(CreateInvoiceForPayment::class);

    expect($action->handle($payment))->toBe('mb_invoice_2')
        ->and($payment->refresh()->moneybird_invoice_id)->toBe('mb_invoice_2')
        ->and($this->membership->refresh()->moneybird_contact_id)->toBe('mb_contact_1')
        ->and($this->gateway->createdInvoices[0]['lines'][0]['price_cents'])->toBe(7500);

    // Idempotent: a second run does not create another invoice.
    expect($action->handle($payment->refresh()))->toBeNull()
        ->and($this->gateway->createdInvoices)->toHaveCount(1);
});

test('unpaid payments and clubs without Moneybird are skipped', function () {
    $openPayment = Payment::factory()->for($this->club)->create(['user_id' => $this->member->id]);

    expect(app(CreateInvoiceForPayment::class)->handle($openPayment))->toBeNull();

    $this->club->update(['moneybird_access_token' => null, 'moneybird_administration_id' => null]);
    $paidPayment = Payment::factory()->for($this->club)->paid()->create(['user_id' => $this->member->id]);

    expect(app(CreateInvoiceForPayment::class)->handle($paidPayment))->toBeNull()
        ->and($this->gateway->createdInvoices)->toHaveCount(0);
});

test('an invoice job is dispatched when a payment becomes paid', function () {
    Queue::fake();

    $payment = Payment::factory()->for($this->club)->create(['user_id' => $this->member->id]);

    app(ApplyPaymentStatus::class)->handle($payment, new MolliePaymentResult(
        id: $payment->mollie_payment_id,
        status: PaymentStatus::Paid,
        checkoutUrl: null,
    ));

    Queue::assertPushed(CreateMoneybirdInvoice::class, fn (CreateMoneybirdInvoice $job) => $job->payment->is($payment));
});
