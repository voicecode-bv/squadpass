<?php

use App\Actions\Moneybird\CreateCreditInvoice;
use App\Enums\StaffRole;
use App\Exceptions\MoneybirdException;
use App\Livewire\Invoices\Index;
use App\Models\Club;
use App\Models\MoneybirdInvoice;
use App\Models\User;
use App\Services\Moneybird\MoneybirdGateway;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\Fakes\FakeMoneybirdGateway;

beforeEach(function () {
    $this->gateway = new FakeMoneybirdGateway;
    $this->app->instance(MoneybirdGateway::class, $this->gateway);

    $this->club = Club::factory()->create([
        'moneybird_access_token' => 'mb_token',
        'moneybird_administration_id' => '123456',
    ]);
    $this->staff = User::factory()->create();
    $this->club->addStaff($this->staff, StaffRole::Admin);
    $this->membership = $this->club->addMember(User::factory()->create());

    $this->gateway->invoices['mb_invoice_1'] = [
        'id' => 'mb_invoice_1',
        'contact_id' => 'mb_contact_9',
        'invoice_id' => '2026-0001',
        'state' => 'open',
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

    $this->invoice = MoneybirdInvoice::factory()->for($this->club)->create([
        'membership_id' => $this->membership->id,
        'moneybird_id' => 'mb_invoice_1',
        'moneybird_contact_id' => 'mb_contact_9',
        'invoice_number' => '2026-0001',
        'total_price_incl_tax_cents' => 7500,
    ]);
});

test('staff can turn an invoice into a credit invoice', function () {
    Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('createCreditInvoice', $this->invoice->id);

    $creditInvoice = MoneybirdInvoice::query()->where('is_credit_invoice', true)->firstOrFail();

    expect($this->gateway->createdCreditInvoices)->toHaveCount(1)
        ->and($this->gateway->createdCreditInvoices[0]['invoice_id'])->toBe('mb_invoice_1')
        ->and($creditInvoice->credited_invoice_id)->toBe($this->invoice->id)
        ->and($creditInvoice->total_price_incl_tax_cents)->toBe(-7500)
        ->and($creditInvoice->membership_id)->toBe($this->membership->id)
        ->and($this->invoice->refresh()->isCreditable())->toBeFalse();
});

test('an invoice cannot be credited twice', function () {
    app(CreateCreditInvoice::class)->handle($this->invoice);

    expect(fn () => app(CreateCreditInvoice::class)->handle($this->invoice->refresh()))
        ->toThrow(MoneybirdException::class);

    expect($this->gateway->createdCreditInvoices)->toHaveCount(1);
});

test('a credit invoice cannot be credited again', function () {
    $creditInvoice = MoneybirdInvoice::factory()->for($this->club)->creditInvoice()->create();

    expect(fn () => app(CreateCreditInvoice::class)->handle($creditInvoice))
        ->toThrow(MoneybirdException::class);
});

test('crediting fails when the club has no Moneybird administration', function () {
    $this->club->update(['moneybird_access_token' => null, 'moneybird_administration_id' => null]);

    expect(fn () => app(CreateCreditInvoice::class)->handle($this->invoice->fresh()))
        ->toThrow(MoneybirdException::class);
});

test('non-staff cannot credit an invoice', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class, ['club' => $this->club])
        ->assertForbidden();

    expect($this->gateway->createdCreditInvoices)->toBeEmpty();
});

test('staff cannot credit an invoice from another club', function () {
    $otherInvoice = MoneybirdInvoice::factory()->create();

    expect(fn () => Livewire::actingAs($this->staff)
        ->test(Index::class, ['club' => $this->club])
        ->call('createCreditInvoice', $otherInvoice->id)
    )->toThrow(ModelNotFoundException::class);

    expect($this->gateway->createdCreditInvoices)->toBeEmpty();
});
