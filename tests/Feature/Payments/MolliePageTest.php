<?php

use App\Enums\AddonKey;
use App\Enums\StaffRole;
use App\Livewire\Clubs\AddonCta;
use App\Livewire\Clubs\Mollie;
use App\Models\Club;
use App\Models\Payment;
use App\Models\User;
use App\Services\Mollie\HttpMollieGateway;
use App\Services\Mollie\MollieGateway;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mollie\Api\Fake\MockMollieClient;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Http\Requests\GetCurrentProfileRequest;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Api\MollieApiClient;
use Tests\Fakes\FakeMollieGateway;

beforeEach(function () {
    $this->gateway = new FakeMollieGateway;
    $this->app->instance(MollieGateway::class, $this->gateway);

    $this->club = Club::factory()->withAddon(AddonKey::Mollie)->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

test('an owner connects Mollie with a key that Mollie accepts', function () {
    Livewire::actingAs($this->owner)
        ->test(Mollie::class, ['club' => $this->club])
        ->set('mollie_api_key', 'test_'.str_repeat('a', 30))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Fake Sportschool');

    $this->club->refresh();

    expect($this->club->mollie_api_key)->toBe('test_'.str_repeat('a', 30))
        ->and($this->club->mollie_profile_id)->toBe('pfl_fake')
        ->and($this->club->mollie_profile_name)->toBe('Fake Sportschool');
});

test('a key Mollie rejects is not stored', function () {
    $this->gateway->rejectedKeys = ['live_'.str_repeat('x', 30)];

    Livewire::actingAs($this->owner)
        ->test(Mollie::class, ['club' => $this->club])
        ->set('mollie_api_key', 'live_'.str_repeat('x', 30))
        ->call('save')
        ->assertHasErrors(['mollie_api_key']);

    expect($this->club->refresh()->mollie_api_key)->toBeNull();
});

test('a mollie api key must start with test_ or live_', function () {
    Livewire::actingAs($this->owner)
        ->test(Mollie::class, ['club' => $this->club])
        ->set('mollie_api_key', 'invalid_key')
        ->call('save')
        ->assertHasErrors(['mollie_api_key' => 'starts_with']);
});

test('disconnecting removes the key and its profile', function () {
    $this->club->update(['mollie_api_key' => 'test_old', 'mollie_profile_name' => 'Oud profiel']);

    Livewire::actingAs($this->owner)
        ->test(Mollie::class, ['club' => $this->club])
        ->call('disconnect')
        ->assertSet('mollie_api_key', '');

    $this->club->refresh();

    expect($this->club->mollie_api_key)->toBeNull()
        ->and($this->club->mollie_profile_name)->toBeNull();
});

test('the mollie api key is stored encrypted', function () {
    $club = Club::factory()->withMollieKey('test_secret')->create();

    $raw = DB::table('clubs')->where('id', $club->id)->value('mollie_api_key');

    expect($raw)->not->toContain('test_secret')
        ->and($club->refresh()->mollie_api_key)->toBe('test_secret');
});

test('an admin sees the payment overview but cannot change the key', function () {
    $this->club->update(['mollie_api_key' => 'test_existing']);
    $admin = User::factory()->create();
    $this->club->addStaff($admin, StaffRole::Admin);

    Livewire::actingAs($admin)
        ->test(Mollie::class, ['club' => $this->club])
        ->assertOk()
        ->assertSet('mollie_api_key', '')
        ->assertSee(__('Only the club owner can change the Mollie connection.'))
        ->set('mollie_api_key', 'test_admin_override')
        ->call('save')
        ->assertForbidden();

    expect($this->club->refresh()->mollie_api_key)->toBe('test_existing');
});

test('members cannot open the Mollie page', function () {
    $member = User::factory()->create();
    $this->club->addMember($member);

    Livewire::actingAs($member)
        ->test(Mollie::class, ['club' => $this->club])
        ->assertForbidden();
});

test('without the add-on the page pitches it and refuses a key', function () {
    $club = Club::factory()->create();
    $club->addStaff($this->owner, StaffRole::Owner);

    Livewire::actingAs($this->owner)
        ->test(Mollie::class, ['club' => $club])
        ->assertSeeLivewire(AddonCta::class)
        ->set('mollie_api_key', 'test_'.str_repeat('a', 30))
        ->call('save')
        ->assertForbidden();
});

test('the payment overview is scoped to the club, filters on status and links to members', function () {
    $member = User::factory()->create(['name' => 'Betalend Lid']);
    $membership = $this->club->addMember($member);
    Payment::factory()->for($this->club)->paid()->create(['user_id' => $member->id, 'amount_cents' => 1234]);
    Payment::factory()->for($this->club)->create(['user_id' => $member->id, 'amount_cents' => 5678]);
    Payment::factory()->paid()->create(['amount_cents' => 9999]);

    Livewire::actingAs($this->owner)
        ->withQueryParams(['tab' => 'payments'])
        ->test(Mollie::class, ['club' => $this->club])
        ->assertSee('12,34')
        ->assertSee('56,78')
        ->assertDontSee('99,99')
        ->assertSee(route('clubs.members.show', [$this->club, $membership]))
        ->set('status', 'paid')
        ->assertSee('12,34')
        ->assertDontSee('56,78');
});

test('an unknown tab or status falls back to the defaults', function () {
    Livewire::actingAs($this->owner)
        ->withQueryParams(['tab' => 'nonsense', 'status' => 'nonsense'])
        ->test(Mollie::class, ['club' => $this->club])
        ->assertSet('tab', 'connection')
        ->assertSet('status', '');
});

test('a failed Mollie request is recorded on the club and cleared by the next success', function () {
    $club = Club::factory()->withMollieKey('test_'.str_repeat('a', 30))->create();
    app()->instance(MollieApiClient::class, new MockMollieClient([
        GetPaymentRequest::class => MockResponse::notFound('No payment exists with token tr_missing.'),
    ]));

    expect(fn () => app(HttpMollieGateway::class)->getPayment($club, 'tr_missing'))->toThrow(Exception::class)
        ->and($club->refresh()->mollie_last_error)->toContain('tr_missing');

    app()->instance(MollieApiClient::class, new MockMollieClient([
        GetPaymentRequest::class => MockResponse::ok([
            'resource' => 'payment', 'id' => 'tr_ok', 'mode' => 'test', 'status' => 'paid',
            'amount' => ['value' => '10.00', 'currency' => 'EUR'], 'description' => 'Les', '_links' => [],
        ]),
    ]));

    app(HttpMollieGateway::class)->getPayment($club, 'tr_ok');

    expect($club->refresh()->mollie_last_error)->toBeNull();
});

test('the gateway returns the profile for a valid key and null for a rejected one', function () {
    app()->instance(MollieApiClient::class, new MockMollieClient([
        GetCurrentProfileRequest::class => MockResponse::ok([
            'resource' => 'profile', 'id' => 'pfl_abc', 'mode' => 'test', 'name' => 'Sportschool Noord',
        ]),
    ]));

    $profile = app(HttpMollieGateway::class)->fetchProfile('test_'.str_repeat('a', 30));

    expect($profile->id)->toBe('pfl_abc')
        ->and($profile->name)->toBe('Sportschool Noord');

    app()->instance(MollieApiClient::class, new MockMollieClient([
        GetCurrentProfileRequest::class => MockResponse::error(401, 'Unauthorized Request', 'Missing authentication, or failed to authenticate'),
    ]));

    expect(app(HttpMollieGateway::class)->fetchProfile('live_'.str_repeat('b', 30)))->toBeNull()
        ->and(app(HttpMollieGateway::class)->fetchProfile('not-a-key'))->toBeNull();
});
