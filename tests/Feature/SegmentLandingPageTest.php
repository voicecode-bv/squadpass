<?php

use App\Enums\ClubSegment;

test('every type of business has its own landing page', function (ClubSegment $segment) {
    [$headline] = $segment->headline();

    $this->get(route('segments.show', $segment))
        ->assertOk()
        ->assertSee('<title>'.e($segment->metaTitle()), false)
        ->assertSee('<link rel="canonical" href="'.route('segments.show', $segment).'">', false)
        ->assertSee($headline)
        ->assertSee($segment->faqs()[0]['question'])
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee($segment->signupTitle())
        ->assertSeeLivewire('clubs.signup');
})->with(ClubSegment::cases());

test('the segment pages live under readable Dutch urls', function () {
    expect(route('segments.show', ClubSegment::DanceSchool, false))->toBe('/voor/dansschool');

    $this->get('/voor/onbekend')->assertNotFound();
});

test('the home page links to every segment page', function () {
    $response = $this->get(route('home'))->assertOk();

    foreach (ClubSegment::cases() as $segment) {
        $response->assertSee(route('segments.show', $segment));
    }
});
