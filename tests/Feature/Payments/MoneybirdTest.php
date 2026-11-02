<?php

use App\Actions\Moneybird\CreateCreditInvoiceForPayment;
use App\Actions\Moneybird\CreateInvoiceForPayment;
use App\Actions\Payments\ApplyPaymentStatus;
use App\Enums\MoneybirdInvoiceDelivery;
use App\Enums\MoneybirdInvoiceState;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Jobs\CreateMoneybirdCreditInvoice;
use App\Jobs\CreateMoneybirdInvoice;
use App\Jobs\SyncMoneybirdContact;
use App\Livewire\Members\Show;
use App\Livewire\Settings\Profile;
use App\Models\Club;
use App\Models\MoneybirdInvoice;
use App\Models\Payment;
use App\Models\StripCardPlan;
use App\Models\User;
use App\Services\Mollie\MolliePaymentResult;
use App\Services\Moneybird\HttpMoneybirdGateway;
use App\Services\Moneybird\MoneybirdGateway;
use Illuminate\Http\Client\RequestException;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Http;
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

test('the name prefix is sent to Moneybird in front of the last name', function () {
    $this->member->update(['first_name' => 'Sanne', 'name_prefix' => 'van der', 'last_name' => 'Berg']);

    Livewire::actingAs($this->owner)
        ->test(Show::class, ['club' => $this->club, 'membership' => $this->membership])
        ->call('syncMoneybird');

    expect($this->gateway->upsertedContacts[0]['attributes']['firstname'])->toBe('Sanne')
        ->and($this->gateway->upsertedContacts[0]['attributes']['lastname'])->toBe('van der Berg');
});

test('a member is linked to the existing Moneybird contact with their customer number instead of creating a duplicate', function () {
    $this->gateway->existingContacts[(string) $this->membership->membership_number] = ['id' => 'mb_existing', 'email' => strtoupper($this->member->email)];

    Livewire::actingAs($this->owner)
        ->test(Show::class, ['club' => $this->club, 'membership' => $this->membership])
        ->call('syncMoneybird');

    expect($this->membership->refresh()->moneybird_contact_id)->toBe('mb_existing')
        ->and($this->gateway->upsertedContacts[0]['contact_id'])->toBe('mb_existing');
});

test('a customer number taken by someone else in Moneybird is reported, not overwritten', function () {
    $this->gateway->existingContacts[(string) $this->membership->membership_number] = ['id' => 'mb_someone_else', 'email' => 'iemand.anders@example.com'];

    Livewire::actingAs($this->owner)
        ->test(Show::class, ['club' => $this->club, 'membership' => $this->membership])
        ->call('syncMoneybird')
        ->assertOk();

    expect($this->membership->refresh()->moneybird_contact_id)->toBeNull()
        ->and($this->gateway->upsertedContacts)->toBeEmpty()
        ->and($this->club->refresh()->moneybird_last_error)->toContain('iemand.anders@example.com');
});

test('the gateway looks up a contact by customer number and treats an unknown number as none', function () {
    Http::fake([
        'moneybird.com/api/v2/123456/contacts/customer_id/7.json' => Http::response(['id' => 42, 'email' => '', 'send_invoices_to_email' => 'mo@example.com']),
        'moneybird.com/api/v2/123456/contacts/customer_id/8.json' => Http::response(['error' => 'Contact not found'], 404),
    ]);
    $gateway = app(HttpMoneybirdGateway::class);

    expect($gateway->findContactByCustomerId($this->club, '7'))->toBe(['id' => '42', 'email' => 'mo@example.com'])
        ->and($gateway->findContactByCustomerId($this->club, '8'))->toBeNull();
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

    // The invoice is mirrored locally so it shows up in the club's overview.
    $mirrored = MoneybirdInvoice::query()->firstOrFail();

    expect($mirrored->moneybird_id)->toBe('mb_invoice_2')
        ->and($mirrored->payment_id)->toBe($payment->id)
        ->and($mirrored->membership_id)->toBe($this->membership->id)
        ->and($mirrored->total_price_incl_tax_cents)->toBe(7500)
        ->and($mirrored->state)->toBe(MoneybirdInvoiceState::Draft)
        ->and($mirrored->is_credit_invoice)->toBeFalse();
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

test('a retry after a partial run finishes the invoice without creating, sending or paying it twice', function () {
    $this->club->update(['moneybird_invoice_delivery' => MoneybirdInvoiceDelivery::Email]);
    $payment = Payment::factory()->for($this->club)->paid()->create(['user_id' => $this->member->id, 'amount_cents' => 3950]);
    $action = app(CreateInvoiceForPayment::class);

    // First run: the invoice was created and sent, then Moneybird rate-limited registering the payment.
    $action->handle($payment);
    MoneybirdInvoice::query()->delete();
    $this->gateway->invoices['mb_invoice_2']['state'] = 'open';
    $this->gateway->registeredPayments = [];

    expect($action->handle($payment->refresh()))->toBe('mb_invoice_2')
        ->and($this->gateway->createdInvoices)->toHaveCount(1)
        ->and($this->gateway->sentInvoices)->toHaveCount(1)
        ->and($this->gateway->registeredPayments)->toHaveCount(1)
        ->and(MoneybirdInvoice::query()->sole()->state)->toBe(MoneybirdInvoiceState::Paid);

    // Fully processed now: nothing left to do.
    expect($action->handle($payment->refresh()))->toBeNull()
        ->and($this->gateway->registeredPayments)->toHaveCount(1);
});

test('a retry finalizes an invoice that was left as a draft', function () {
    $this->club->update(['moneybird_invoice_delivery' => MoneybirdInvoiceDelivery::Manual]);
    $payment = Payment::factory()->for($this->club)->paid()->create(['user_id' => $this->member->id]);
    $this->gateway->createInvoice($this->club, 'mb_contact_x', [['description' => 'Les', 'price_cents' => 1000]]);
    $payment->update(['moneybird_invoice_id' => 'mb_invoice_1']);

    app(CreateInvoiceForPayment::class)->handle($payment);

    expect($this->gateway->createdInvoices)->toHaveCount(1)
        ->and($this->gateway->sentInvoices[0]['invoice_id'])->toBe('mb_invoice_1')
        ->and($this->gateway->registeredPayments[0]['invoice_id'])->toBe('mb_invoice_1')
        ->and(MoneybirdInvoice::query()->sole()->payment_id)->toBe($payment->id);
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

    // The credit invoice is mirrored locally too.
    expect(MoneybirdInvoice::query()->where('moneybird_id', 'mb_credit_1')->firstOrFail()->is_credit_invoice)->toBeTrue();
});

test('a retry finalizes a credit invoice that was left as a draft', function () {
    $this->club->update(['moneybird_invoice_delivery' => MoneybirdInvoiceDelivery::Manual]);
    $payment = Payment::factory()->for($this->club)->create([
        'user_id' => $this->member->id,
        'status' => PaymentStatus::Refunded,
        'moneybird_invoice_id' => 'mb_invoice_9',
    ]);
    $creditInvoiceId = $this->gateway->createCreditInvoice($this->club, 'mb_invoice_9');
    $payment->update(['moneybird_credit_invoice_id' => $creditInvoiceId]);

    expect(app(CreateCreditInvoiceForPayment::class)->handle($payment))->toBe($creditInvoiceId)
        ->and($this->gateway->createdCreditInvoices)->toHaveCount(1)
        ->and($this->gateway->sentInvoices[0]['invoice_id'])->toBe($creditInvoiceId)
        ->and($this->gateway->settledCreditInvoices)->toBe([$creditInvoiceId]);
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
        ->set('last_name', 'Neybird-Jansen')
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

/**
 * Run a queued job through its middleware the way the queue worker does.
 */
function runThroughMiddleware(object $job): void
{
    (new Pipeline(app()))
        ->send($job)
        ->through($job->middleware())
        ->then(fn (object $job) => app()->call([$job, 'handle']));
}

test('a Moneybird rate limit releases the invoice job until the Retry-After delay instead of failing it', function () {
    $this->app->bind(MoneybirdGateway::class, HttpMoneybirdGateway::class);
    Http::fake(['moneybird.com/*' => Http::response('Retry later', 429, ['Retry-After' => '120'])]);
    $payment = Payment::factory()->for($this->club)->paid()->create(['user_id' => $this->member->id]);

    $job = (new CreateMoneybirdInvoice($payment))->withFakeQueueInteractions();

    runThroughMiddleware($job);

    $job->assertReleased(delay: 120)->assertNotFailed();
    expect($job->retryUntil())->toBeInstanceOf(DateTimeInterface::class)
        ->and($job->maxExceptions)->toBe(3)
        ->and($payment->refresh()->moneybird_invoice_id)->toBeNull();
});

test('other Moneybird errors still throw from the invoice job', function () {
    $this->app->bind(MoneybirdGateway::class, HttpMoneybirdGateway::class);
    Http::fake(['moneybird.com/*' => Http::response('Unprocessable', 422)]);
    $payment = Payment::factory()->for($this->club)->paid()->create(['user_id' => $this->member->id]);

    $job = (new CreateMoneybirdInvoice($payment))->withFakeQueueInteractions();

    expect(fn () => runThroughMiddleware($job))->toThrow(RequestException::class);
    $job->assertNotReleased();
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
