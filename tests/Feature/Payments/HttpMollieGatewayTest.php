<?php

use App\Enums\PaymentStatus;
use App\Models\Club;
use App\Services\Mollie\HttpMollieGateway;
use Mollie\Api\Fake\MockMollieClient;
use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Http\PendingRequest;
use Mollie\Api\Http\Requests\CreateCustomerRequest;
use Mollie\Api\Http\Requests\CreatePaymentRequest;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Api\MollieApiClient;

/**
 * @param  array<string, mixed>  $payment
 */
function fakeMolliePayment(array $payment): void
{
    app()->instance(MollieApiClient::class, new MockMollieClient([
        GetPaymentRequest::class => MockResponse::ok(array_merge([
            'resource' => 'payment',
            'id' => 'tr_test123',
            'mode' => 'test',
            'amount' => ['value' => '10.00', 'currency' => 'EUR'],
            'description' => 'Strippenkaart',
            'metadata' => ['purpose' => 'strip_card'],
            '_links' => [],
        ], $payment)),
    ]));
}

test('a Mollie payment is mapped to our status, checkout url, mandate and metadata', function (string $mollieStatus, PaymentStatus $expected) {
    $club = Club::factory()->withMollieKey('test_'.str_repeat('a', 30))->create();
    fakeMolliePayment([
        'status' => $mollieStatus,
        'mandateId' => 'mdt_abc',
        '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/test', 'type' => 'text/html']],
    ]);

    $result = app(HttpMollieGateway::class)->getPayment($club, 'tr_test123');

    expect($result->id)->toBe('tr_test123')
        ->and($result->status)->toBe($expected)
        ->and($result->checkoutUrl)->toBe('https://www.mollie.com/checkout/test')
        ->and($result->mandateId)->toBe('mdt_abc')
        ->and($result->metadata)->toBe(['purpose' => 'strip_card']);
})->with([
    ['open', PaymentStatus::Open],
    ['pending', PaymentStatus::Open],
    ['authorized', PaymentStatus::Open],
    ['paid', PaymentStatus::Paid],
    ['failed', PaymentStatus::Failed],
]);

test('a one-off Mollie payment without mandate or checkout link maps to null values', function () {
    $club = Club::factory()->withMollieKey('test_'.str_repeat('a', 30))->create();
    fakeMolliePayment(['status' => 'paid']);

    $result = app(HttpMollieGateway::class)->getPayment($club, 'tr_test123');

    expect($result->checkoutUrl)->toBeNull()
        ->and($result->mandateId)->toBeNull();
});

test('creating a customer and a payment sends the expected requests', function () {
    $club = Club::factory()->withMollieKey('test_'.str_repeat('a', 30))->create();
    $client = new MockMollieClient([
        CreateCustomerRequest::class => MockResponse::created(['resource' => 'customer', 'id' => 'cst_abc', 'mode' => 'test', 'name' => 'Anna', 'email' => 'anna@example.com']),
        CreatePaymentRequest::class => MockResponse::created([
            'resource' => 'payment',
            'id' => 'tr_new',
            'mode' => 'test',
            'status' => 'open',
            'amount' => ['value' => '12.50', 'currency' => 'EUR'],
            'description' => 'Strippenkaart',
            'metadata' => ['purpose' => 'strip_card'],
            '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/new', 'type' => 'text/html']],
        ]),
    ], retainRequests: true);
    app()->instance(MollieApiClient::class, $client);
    $gateway = app(HttpMollieGateway::class);

    expect($gateway->createCustomer($club, 'Anna', 'anna@example.com'))->toBe('cst_abc');

    $result = $gateway->createPayment($club, 1250, 'Strippenkaart', 'https://example.com/return', 'https://example.com/webhook', customerId: 'cst_abc', metadata: ['purpose' => 'strip_card']);

    expect($result->id)->toBe('tr_new')
        ->and($result->status)->toBe(PaymentStatus::Open)
        ->and($result->checkoutUrl)->toBe('https://www.mollie.com/checkout/new');

    $client->assertSent(fn (PendingRequest $pendingRequest) => $pendingRequest->getRequest() instanceof CreatePaymentRequest
        && $pendingRequest->payload()->all()['amount']['value'] === '12.50'
        && $pendingRequest->payload()->all()['customerId'] === 'cst_abc');
});
