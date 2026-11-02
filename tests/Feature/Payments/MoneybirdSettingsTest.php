<?php

use App\Enums\AddonKey;
use App\Enums\MoneybirdInvoiceDelivery;
use App\Enums\StaffRole;
use App\Jobs\CreateMoneybirdInvoice;
use App\Jobs\SyncMoneybirdContact;
use App\Livewire\Clubs\AddonCta;
use App\Livewire\Clubs\Moneybird;
use App\Models\Club;
use App\Models\Payment;
use App\Models\User;
use App\Services\Moneybird\MoneybirdGateway;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Fakes\FakeMoneybirdGateway;

beforeEach(function () {
    $this->gateway = new FakeMoneybirdGateway;
    $this->app->instance(MoneybirdGateway::class, $this->gateway);

    $this->owner = User::factory()->create();
});

function clubWithOwner($test, bool $connected = false): Club
{
    $factory = $connected ? Club::factory()->withMoneybird() : Club::factory()->withAddon(AddonKey::Moneybird);
    $club = $factory->create();
    $club->addStaff($test->owner, StaffRole::Owner);

    return $club;
}

test('the page asks to activate the add-on when the club does not have it', function () {
    $club = Club::factory()->create();
    $club->addStaff($this->owner, StaffRole::Owner);

    Livewire::actingAs($this->owner)
        ->test(Moneybird::class, ['club' => $club])
        ->assertSeeLivewire(AddonCta::class)
        ->set('access_token', 'a-valid-looking-token-value')
        ->call('connect')
        ->assertForbidden();
});

test('connecting with a valid token and a single administration links the club directly', function () {
    $club = clubWithOwner($this);

    Livewire::actingAs($this->owner)
        ->test(Moneybird::class, ['club' => $club])
        ->assertSet('step', 1)
        ->set('access_token', 'a-valid-looking-token-value')
        ->call('connect')
        ->assertHasNoErrors()
        ->assertSet('step', 3)
        ->assertSee(__('Connected'));

    $club->refresh();

    expect($club->moneybird_access_token)->toBe('a-valid-looking-token-value')
        ->and($club->moneybird_administration_id)->toBe('123456')
        ->and($club->hasMoneybirdConfigured())->toBeTrue();
});

test('connecting with a rejected token shows an error and stores nothing', function () {
    $club = clubWithOwner($this);
    $this->gateway->invalidTokens = ['a-rejected-token-value-here'];

    Livewire::actingAs($this->owner)
        ->test(Moneybird::class, ['club' => $club])
        ->set('access_token', 'a-rejected-token-value-here')
        ->call('connect')
        ->assertHasErrors(['access_token']);

    expect($club->refresh()->moneybird_access_token)->toBeNull();
});

test('with multiple administrations the owner picks one', function () {
    $club = clubWithOwner($this);
    $this->gateway->administrations = [
        ['id' => '111', 'name' => 'Hoofdvestiging'],
        ['id' => '222', 'name' => 'Stichting Jeugd'],
    ];

    Livewire::actingAs($this->owner)
        ->test(Moneybird::class, ['club' => $club])
        ->set('access_token', 'a-valid-looking-token-value')
        ->call('connect')
        ->assertSet('step', 2)
        ->assertSee('Stichting Jeugd')
        ->set('administration_id', '999')
        ->call('selectAdministration')
        ->assertHasErrors(['administration_id'])
        ->set('administration_id', '222')
        ->call('selectAdministration')
        ->assertHasNoErrors()
        ->assertSet('step', 3);

    expect($club->refresh()->moneybird_administration_id)->toBe('222');
});

test('invoice settings are loaded from Moneybird and saved on the club', function () {
    $club = clubWithOwner($this, connected: true);

    Livewire::actingAs($this->owner)
        ->test(Moneybird::class, ['club' => $club])
        ->assertSee('Omzet abonnementen')
        ->assertSee('21% BTW')
        ->set('ledger_account_id', 'la_2')
        ->set('tax_rate_id', 'tr_21')
        ->set('workflow_id', 'wf_1')
        ->set('document_style_id', 'ds_1')
        ->set('invoice_delivery', 'email')
        ->set('sync_contacts', false)
        ->call('saveSettings')
        ->assertHasNoErrors();

    $club->refresh();

    expect($club->moneybird_ledger_account_id)->toBe('la_2')
        ->and($club->moneybird_tax_rate_id)->toBe('tr_21')
        ->and($club->moneybird_workflow_id)->toBe('wf_1')
        ->and($club->moneybird_document_style_id)->toBe('ds_1')
        ->and($club->moneybird_invoice_delivery)->toBe(MoneybirdInvoiceDelivery::Email)
        ->and($club->moneybird_sync_contacts)->toBeFalse();
});

test('disconnecting clears the credentials and invoice settings', function () {
    $club = clubWithOwner($this, connected: true);
    $club->update(['moneybird_ledger_account_id' => 'la_1']);

    Livewire::actingAs($this->owner)
        ->test(Moneybird::class, ['club' => $club])
        ->call('disconnect');

    $club->refresh();

    expect($club->moneybird_access_token)->toBeNull()
        ->and($club->moneybird_administration_id)->toBeNull()
        ->and($club->moneybird_setup_completed_at)->toBeNull()
        ->and($club->moneybird_ledger_account_id)->toBeNull()
        ->and($club->hasMoneybirdConfigured())->toBeFalse();
});

test('the catch-up action queues invoices for paid payments without one', function () {
    Queue::fake();
    $club = clubWithOwner($this, connected: true);
    $member = User::factory()->create();
    $club->addMember($member);
    Payment::factory()->for($club)->paid()->count(3)->create(['user_id' => $member->id]);

    Livewire::actingAs($this->owner)
        ->test(Moneybird::class, ['club' => $club])
        ->assertSee(trans_choice(':count paid payment without invoice|:count paid payments without invoice', 3))
        ->call('invoiceMissingPayments');

    Queue::assertPushed(CreateMoneybirdInvoice::class, 3);
});

test('a club admin cannot manage the Moneybird connection', function () {
    $club = clubWithOwner($this, connected: true);
    $admin = User::factory()->create();
    $club->addStaff($admin, StaffRole::Admin);

    Livewire::actingAs($admin)
        ->test(Moneybird::class, ['club' => $club])
        ->assertForbidden();
});

test('the wizard saves each step and finishing completes the setup and catches up on existing data', function () {
    $club = Club::factory()->withUnfinishedMoneybirdSetup()->create();
    $club->addStaff($this->owner, StaffRole::Owner);
    $member = User::factory()->create();
    $club->addMember($member);
    Queue::fake();
    Payment::factory()->for($club)->paid()->count(2)->create(['user_id' => $member->id]);

    Livewire::actingAs($this->owner)
        ->test(Moneybird::class, ['club' => $club])
        ->assertSet('step', 3)
        ->set('ledger_account_id', 'la_2')
        ->set('tax_rate_id', 'tr_9')
        ->call('saveAccounting')
        ->assertSet('step', 4)
        ->set('invoice_delivery', 'manual')
        ->set('workflow_id', 'wf_1')
        ->call('saveInvoicing')
        ->assertSet('step', 5)
        ->assertSee(trans_choice('Create an invoice for :count earlier payment|Create invoices for :count earlier payments', 2))
        ->set('sync_contacts', false)
        ->call('finishSetup')
        ->assertHasNoErrors()
        ->assertSet('step', 0);

    $club->refresh();

    expect($club->moneybird_ledger_account_id)->toBe('la_2')
        ->and($club->moneybird_tax_rate_id)->toBe('tr_9')
        ->and($club->moneybird_invoice_delivery)->toBe(MoneybirdInvoiceDelivery::Manual)
        ->and($club->moneybird_workflow_id)->toBe('wf_1')
        ->and($club->moneybird_sync_contacts)->toBeFalse()
        ->and($club->moneybird_setup_completed_at)->not->toBeNull();

    Queue::assertPushed(CreateMoneybirdInvoice::class, 2);
    Queue::assertPushed(SyncMoneybirdContact::class, 1);
});

test('finishing the wizard without catching up queues nothing', function () {
    $club = Club::factory()->withUnfinishedMoneybirdSetup()->create();
    $club->addStaff($this->owner, StaffRole::Owner);
    $member = User::factory()->create();
    $club->addMember($member);
    Queue::fake();
    Payment::factory()->for($club)->paid()->create(['user_id' => $member->id]);

    Livewire::actingAs($this->owner)
        ->test(Moneybird::class, ['club' => $club])
        ->set('invoice_existing_payments', false)
        ->set('sync_existing_members', false)
        ->call('finishSetup')
        ->assertSet('step', 0);

    Queue::assertNothingPushed();
});

test('an unfinished setup resumes at the bookkeeping step and cannot go back past the connection', function () {
    $club = Club::factory()->withUnfinishedMoneybirdSetup()->create();
    $club->addStaff($this->owner, StaffRole::Owner);

    Livewire::actingAs($this->owner)
        ->test(Moneybird::class, ['club' => $club])
        ->assertSet('step', 3)
        ->call('previousStep')
        ->assertSet('step', 3);
});

test('a finished setup shows the overview and can be walked through again', function () {
    $club = clubWithOwner($this, connected: true);

    Livewire::actingAs($this->owner)
        ->test(Moneybird::class, ['club' => $club])
        ->assertSet('step', 0)
        ->assertSee(__('Run setup again'))
        ->call('restartSetup')
        ->assertSet('step', 3);
});

test('the wizard steps cannot be saved before Moneybird is connected', function (string $action) {
    $club = clubWithOwner($this);

    Livewire::actingAs($this->owner)
        ->test(Moneybird::class, ['club' => $club])
        ->call($action)
        ->assertForbidden();
})->with(['saveAccounting', 'saveInvoicing', 'finishSetup', 'restartSetup']);
