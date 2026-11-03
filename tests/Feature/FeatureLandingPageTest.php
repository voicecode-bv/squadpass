<?php

use App\Enums\ClubSegment;
use App\Enums\MarketingFeature;

test('every feature has its own landing page', function (MarketingFeature $feature) {
    [$headline] = $feature->headline();

    $this->get(route('features.show', $feature))
        ->assertOk()
        ->assertSee('<title>'.e($feature->metaTitle()), false)
        ->assertSee('<link rel="canonical" href="'.route('features.show', $feature).'">', false)
        ->assertSee($headline)
        ->assertSee($feature->benefits()[0]['title'])
        ->assertSee($feature->faqs()[0]['question'])
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSeeLivewire('clubs.signup');
})->with(MarketingFeature::cases());

test('the feature pages live under readable Dutch urls', function () {
    expect(route('features.show', MarketingFeature::DirectDebit, false))->toBe('/functies/automatische-incasso')
        ->and(route('features.index', absolute: false))->toBe('/functies');

    $this->get('/functies/onbekend')->assertNotFound();
});

test('the overview links to every feature and segment page', function () {
    $response = $this->get(route('features.index'))->assertOk();

    foreach (MarketingFeature::cases() as $feature) {
        $response->assertSee(route('features.show', $feature));
    }

    foreach (ClubSegment::cases() as $segment) {
        $response->assertSee(route('segments.show', $segment));
    }
});

test('the home page links to the feature pages', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('features.index'))
        ->assertSee(route('features.show', MarketingFeature::MemberManagement))
        ->assertSee(route('features.show', MarketingFeature::WhiteLabel));
});
