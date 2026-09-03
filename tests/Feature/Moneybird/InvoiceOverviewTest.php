<?php

use App\Enums\MoneybirdInvoiceState;
use App\Enums\StaffRole;
use App\Livewire\Invoices\Index;
use App\Models\Club;
use App\Models\MoneybirdInvoice;
use App\Models\User;
use App\Services\Moneybird\MoneybirdGateway;
use Livewire\Livewire;
use Tests\Fakes\FakeMoneybirdGateway;

beforeEach(function () {
    $this->gateway = new FakeMoneybirdGateway;
    $this->app->instance(MoneybirdGateway::class, $this->gateway);

    $this->club = Club::factory()->withMoneybird()->create(['moneybird_sync_contacts' => false]);
    $this->staff = User::factory()->create();
    $this->club->addStaff($this->staff, StaffRole::Admin);

    $this->member = User::factory()->create(['name' => 'Anna Bakker', 'email' => 'anna@example.com']);
    $this->membership = $this->club->addMember($this->member);
});

test('staff see only the invoices of their own club', function () {
    $own = MoneybirdInvoice::factory()->for($this->club)->create(['invoice_number' => '2026-0001']);
    $other = MoneybirdInvoice::factory()->create(['invoice_number' => '2026-9999']);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->assertSee('2026-0001')
        ->assertDontSee('2026-9999');

    expect($other->club_id)->not->toBe($own->club_id);
});

test('the invoice page renders for staff', function () {
    MoneybirdInvoice::factory()->for($this->club)->create(['invoice_number' => '2026-0001']);

    $this->actingAs($this->staff)
        ->get(route('clubs.invoices', $this->club))
        ->assertOk()
        ->assertSee('2026-0001');
});

test('non-staff cannot open the invoice overview', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class, ['club' => $this->club])
        ->assertForbidden();
});

test('invoices can be filtered by member, date and amount', function () {
    $matching = MoneybirdInvoice::factory()->for($this->club)->create([
        'membership_id' => $this->membership->id,
        'invoice_number' => '2026-0001',
        'invoice_date' => '2026-06-15',
        'total_price_incl_tax_cents' => 5000,
    ]);

    $otherMember = $this->club->addMember(User::factory()->create(['name' => 'Bob Jansen']));

    MoneybirdInvoice::factory()->for($this->club)->create([
        'membership_id' => $otherMember->id,
        'invoice_number' => '2026-0002',
        'invoice_date' => '2026-06-15',
        'total_price_incl_tax_cents' => 5000,
    ]);
    MoneybirdInvoice::factory()->for($this->club)->create([
        'membership_id' => $this->membership->id,
        'invoice_number' => '2026-0003',
        'invoice_date' => '2026-01-05',
        'total_price_incl_tax_cents' => 5000,
    ]);
    MoneybirdInvoice::factory()->for($this->club)->create([
        'membership_id' => $this->membership->id,
        'invoice_number' => '2026-0004',
        'invoice_date' => '2026-06-15',
        'total_price_incl_tax_cents' => 99000,
    ]);

    $component = Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('member', 'Anna')
        ->set('from', '2026-06-01')
        ->set('until', '2026-06-30')
        ->set('minAmount', '40')
        ->set('maxAmount', '60');

    $component->assertSee('2026-0001')
        ->assertDontSee('2026-0002')
        ->assertDontSee('2026-0003')
        ->assertDontSee('2026-0004');

    expect($component->instance()->totals)->toBe(['count' => 1, 'total_cents' => 5000])
        ->and($matching->fresh())->not->toBeNull();
});

test('the member filter also matches a membership number', function () {
    MoneybirdInvoice::factory()->for($this->club)->create([
        'membership_id' => $this->membership->id,
        'invoice_number' => '2026-0001',
    ]);
    MoneybirdInvoice::factory()->for($this->club)->create(['invoice_number' => '2026-0002']);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('member', (string) $this->membership->membership_number)
        ->assertSee('2026-0001')
        ->assertDontSee('2026-0002');
});

test('the amount filter matches credit invoices on the credited amount', function () {
    MoneybirdInvoice::factory()->for($this->club)->creditInvoice()->create([
        'invoice_number' => '2026-0009',
        'total_price_incl_tax_cents' => -5000,
    ]);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('minAmount', '40')
        ->set('maxAmount', '60')
        ->assertSee('2026-0009');
});

test('invoices can be filtered by type and state', function () {
    MoneybirdInvoice::factory()->for($this->club)->paid()->create(['invoice_number' => '2026-0001']);
    MoneybirdInvoice::factory()->for($this->club)->creditInvoice()->create(['invoice_number' => '2026-0002']);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->set('type', 'credits')
        ->assertSee('2026-0002')
        ->assertDontSee('2026-0001')
        ->set('type', 'all')
        ->set('state', MoneybirdInvoiceState::Paid->value)
        ->assertSee('2026-0001')
        ->assertDontSee('2026-0002');
});

test('staff can open an invoice to see its lines', function () {
    $invoice = MoneybirdInvoice::factory()->for($this->club)->create([
        'membership_id' => $this->membership->id,
        'invoice_number' => '2026-0007',
        'lines' => [[
            'description' => 'Maandabonnement mei',
            'amount' => '1',
            'price_cents' => 4500,
            'total_price_excl_tax_cents' => 3719,
        ]],
    ]);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('showInvoice', $invoice->id)
        ->assertSet('selectedInvoiceId', $invoice->id)
        ->assertSee('Maandabonnement mei')
        ->assertSee('Anna Bakker');
});

test('an invoice of another club cannot be opened', function () {
    $otherInvoice = MoneybirdInvoice::factory()->create();

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('showInvoice', $otherInvoice->id)
        ->assertSet('selectedInvoiceId', null);
});

test('staff can sync invoices from Moneybird and they are linked to the member', function () {
    $this->membership->update(['moneybird_contact_id' => 'mb_contact_9']);

    $this->gateway->invoices['mb_invoice_1'] = [
        'id' => 'mb_invoice_1',
        'contact_id' => 'mb_contact_9',
        'invoice_id' => '2026-0042',
        'state' => 'paid',
        'invoice_date' => '2026-05-01',
        'currency' => 'EUR',
        'total_price_incl_tax' => '75.00',
        'updated_at' => '2026-05-01T10:00:00.000Z',
        'details' => [[
            'description' => 'Strippenkaart',
            'amount' => '1',
            'price' => '75.00',
            'total_price_excl_tax_with_discount' => '61.98',
        ]],
    ];

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('sync')
        ->assertSee('2026-0042');

    $invoice = MoneybirdInvoice::query()->firstOrFail();

    expect($invoice->membership_id)->toBe($this->membership->id)
        ->and($invoice->total_price_incl_tax_cents)->toBe(7500)
        ->and($invoice->state)->toBe(MoneybirdInvoiceState::Paid)
        ->and($invoice->is_credit_invoice)->toBeFalse()
        ->and($invoice->lines[0]['description'])->toBe('Strippenkaart');
});

test('a second sync only asks Moneybird for invoices changed since the last one', function () {
    MoneybirdInvoice::factory()->for($this->club)->create(['moneybird_updated_at' => '2026-05-01 10:00:00']);

    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('sync');

    expect($this->gateway->listCalls[0]['updated_after'])->not->toBeNull();
});
