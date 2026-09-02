<?php

use App\Actions\Moneybird\CreateCreditInvoiceForPayment;
use App\Actions\Moneybird\CreateInvoiceForPayment;
use App\Actions\Payments\ApplyPaymentStatus;
use App\Enums\MoneybirdInvoiceDelivery;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Jobs\CreateMoneybirdCreditInvoice;
use App\Jobs\CreateMoneybirdInvoice;
use App\Jobs\SyncMoneybirdContact;
use App\Livewire\Members\Show;
use App\Livewire\Settings\Profile;
use App\Models\Club;
use App\Models\Payment;
use App\Models\StripCardPlan;
use App\Models\User;
use App\Services\Mollie\MolliePaymentResult;
use App\Services\Moneybird\MoneybirdGateway;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Fakes\FakeMoneybirdGateway;

beforeEach(function () {
    $this->gateway = new FakeMoneybirdGateway;
    $this->app->instance(MoneybirdGateway::class, $this->gateway);

    $this->club = Club::factory()->withMoneybird('mb_token', '123456')->create(['moneybird_sync_contacts' => false]);
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
        ->and($this->gateway->upsertedContacts[0]['attributes']['customer_id'])->toBe((string) $this->membership->membership_number)
        ->and($this->club->refresh()->moneybird_last_synced_at)->not->toBeNull();
});

test('syncing without Moneybird configuration does nothing', function () {
    $this->club->update(['moneybird_access_token' => null, 'moneybird_administration_id' => null]);

    Livewire::actingAs($this->owner)
        ->test(Show::class, ['club' => $this->club, 'membership' => $this->membership])
        ->call('syncMoneybird');

    expect($this->membership->refresh()->moneybird_contact_id)->toBeNull()
        ->and($this->gateway->upsertedContacts)->toHaveCount(0);
});

test('a paid payment creates a draft invoice with the club settings and syncs the contact once', function () {
    $this->club->update([
        'moneybird_ledger_account_id' => 'la_1',
        'moneybird_tax_rate_id' => 'tr_21',
        'moneybird_workflow_id' => 'wf_1',
        'moneybird_document_style_id' => 'ds_1',
    ]);
    $plan = StripCardPlan::factory()->for($this->club)->create(['name' => '10-strippenkaart']);
    $payment = Payment::factory()->for($this->club)->paid()->create([
        'user_id' => $this->member->id,
        'amount_cents' => 7500,
        'payable_type' => StripCardPlan::class,
        'payable_id' => $plan->id,
        'metadata' => ['purpose' => 'strip_card'],
    ]);

    $action = app(CreateInvoiceForPayment::class);

    expect($action->handle($payment))->toBe('mb_invoice_2')
        ->and($payment->refresh()->moneybird_invoice_id)->toBe('mb_invoice_2')
        ->and($this->membership->refresh()->moneybird_contact_id)->toBe('mb_contact_1');

    $invoice = $this->gateway->createdInvoices[0];

    expect($invoice['lines'][0]['price_cents'])->toBe(7500)
        ->and($invoice['lines'][0]['description'])->toContain('10-strippenkaart')
        ->and($invoice['lines'][0]['tax_rate_id'])->toBe('tr_21')
        ->and($invoice['lines'][0]['ledger_account_id'])->toBe('la_1')
        ->and($invoice['attributes']['workflow_id'])->toBe('wf_1')
        ->and($invoice['attributes']['document_style_id'])->toBe('ds_1')
        ->and($invoice['attributes']['reference'])->toBe("SquadPass #{$payment->id}")
        ->and($invoice['attributes']['prices_are_incl_tax'])->toBeTrue()
        ->and($this->gateway->sentInvoices)->toHaveCount(0)
        ->and($this->gateway->registeredPayments)->toHaveCount(0);

    // Idempotent: a second run does not create another invoice.
    expect($action->handle($payment->refresh()))->toBeNull()
        ->and($this->gateway->createdInvoices)->toHaveCount(1);
});

test('with email delivery the invoice is sent and the Mollie payment is registered on it', function () {
    $this->club->update(['moneybird_invoice_delivery' => MoneybirdInvoiceDelivery::Email]);
    $payment = Payment::factory()->for($this->club)->paid()->create([
        'user_id' => $this->member->id,
        'amount_cents' => 3950,
        'paid_at' => now()->subDays(2),
    ]);

    app(CreateInvoiceForPayment::class)->handle($payment);

    expect($this->gateway->sentInvoices)->toHaveCount(1)
        ->and($this->gateway->sentInvoices[0]['invoice_id'])->toBe('mb_invoice_2')
        ->and($this->gateway->sentInvoices[0]['delivery'])->toBe(MoneybirdInvoiceDelivery::Email)
        ->and($this->gateway->registeredPayments[0]['invoice_id'])->toBe('mb_invoice_2')
        ->and($this->gateway->registeredPayments[0]['amount_cents'])->toBe(3950)
        ->and($this->gateway->registeredPayments[0]['paid_at'])->toBe(now()->subDays(2)->toDateString());
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

test('a refund creates a credit invoice that is finalized and settled when invoices are finalized', function () {
    Queue::fake();
    $this->club->update(['moneybird_invoice_delivery' => MoneybirdInvoiceDelivery::Manual]);

    $payment = Payment::factory()->for($this->club)->paid()->create([
        'user_id' => $this->member->id,
        'moneybird_invoice_id' => 'mb_invoice_9',
    ]);

    app(ApplyPaymentStatus::class)->handle($payment, new MolliePaymentResult(
        id: $payment->mollie_payment_id,
        status: PaymentStatus::Refunded,
        checkoutUrl: null,
    ));

    Queue::assertPushed(CreateMoneybirdCreditInvoice::class);

    $action = app(CreateCreditInvoiceForPayment::class);

    expect($action->handle($payment->refresh()))->toBe('mb_credit_1')
        ->and($payment->refresh()->moneybird_credit_invoice_id)->toBe('mb_credit_1')
        ->and($this->gateway->createdCreditInvoices[0]['invoice_id'])->toBe('mb_invoice_9')
        ->and($this->gateway->sentInvoices[0]['delivery'])->toBe(MoneybirdInvoiceDelivery::Manual)
        ->and($this->gateway->settledCreditInvoices)->toBe(['mb_credit_1']);

    // Idempotent.
    expect($action->handle($payment->refresh()))->toBeNull()
        ->and($this->gateway->createdCreditInvoices)->toHaveCount(1);
});

test('a refund of a payment that was never invoiced creates no credit invoice', function () {
    $payment = Payment::factory()->for($this->club)->create([
        'user_id' => $this->member->id,
        'status' => PaymentStatus::Refunded,
    ]);

    expect(app(CreateCreditInvoiceForPayment::class)->handle($payment))->toBeNull()
        ->and($this->gateway->createdCreditInvoices)->toHaveCount(0);
});

test('new members are synced automatically when contact sync is enabled', function () {
    Queue::fake();
    $this->club->update(['moneybird_sync_contacts' => true]);

    $membership = $this->club->addMember(User::factory()->create());

    Queue::assertPushed(SyncMoneybirdContact::class, fn (SyncMoneybirdContact $job) => $job->membership->is($membership));
});

test('new members are not synced when contact sync is disabled', function () {
    Queue::fake();

    $this->club->addMember(User::factory()->create());

    Queue::assertNotPushed(SyncMoneybirdContact::class);
});

test('changing your name resyncs your Moneybird contacts', function () {
    Queue::fake();
    $this->club->update(['moneybird_sync_contacts' => true]);

    Livewire::actingAs($this->member)
        ->test(Profile::class)
        ->set('name', 'Mo Neybird-Jansen')
        ->set('email', $this->member->email)
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    Queue::assertPushed(SyncMoneybirdContact::class, fn (SyncMoneybirdContact $job) => $job->membership->is($this->membership));
});

test('the invoice backfill command queues invoices for paid payments without one', function () {
    Queue::fake();

    Payment::factory()->for($this->club)->paid()->count(2)->create(['user_id' => $this->member->id]);
    Payment::factory()->for($this->club)->paid()->create(['user_id' => $this->member->id, 'moneybird_invoice_id' => 'mb_invoice_x']);
    Payment::factory()->for($this->club)->create(['user_id' => $this->member->id]);

    $this->artisan('moneybird:invoice-payments', ['club' => $this->club->slug])
        ->expectsOutputToContain('Queued 2')
        ->assertSuccessful();

    Queue::assertPushed(CreateMoneybirdInvoice::class, 2);
});

test('the contact sync command queues a sync for every active member', function () {
    Queue::fake();
    $this->club->addMember(User::factory()->create());

    $this->artisan('moneybird:sync-contacts', ['club' => $this->club->slug])
        ->expectsOutputToContain('Queued 2')
        ->assertSuccessful();

    Queue::assertPushed(SyncMoneybirdContact::class, 2);
});

test('a failed invoice job records the error on the club', function () {
    $payment = Payment::factory()->for($this->club)->paid()->create(['user_id' => $this->member->id]);

    (new CreateMoneybirdInvoice($payment))->failed(new RuntimeException('HTTP 422 Unprocessable Entity'));

    expect($this->club->refresh()->moneybird_last_error)->toBe('HTTP 422 Unprocessable Entity');
});

test('the member page links to the Moneybird invoice', function () {
    Payment::factory()->for($this->club)->paid()->create([
        'user_id' => $this->member->id,
        'moneybird_invoice_id' => '9988',
    ]);

    Livewire::actingAs($this->owner)
        ->test(Show::class, ['club' => $this->club, 'membership' => $this->membership])
        ->assertSee('https://moneybird.com/123456/sales_invoices/9988');
});
