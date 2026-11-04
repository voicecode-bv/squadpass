<?php

use App\Enums\ClubPlan;
use App\Enums\ClubSegment;
use App\Enums\MarketingFeature;

test('the sitemap lists the home page and every segment and feature page', function () {
    $response = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('home').'</loc>', escape: false);

    foreach (ClubSegment::cases() as $segment) {
        $response->assertSee('<loc>'.route('segments.show', $segment).'</loc>', escape: false);
    }

    foreach (MarketingFeature::cases() as $feature) {
        $response->assertSee('<loc>'.route('features.show', $feature).'</loc>', escape: false);
    }

    expect(simplexml_load_string($response->getContent()))->not->toBeFalse();
});

test('robots.txt points crawlers at the sitemap in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: '.route('sitemap'))
        ->assertDontSee('Disallow: /');
});

test('robots.txt keeps other environments out of search results', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /')
        ->assertDontSee('Sitemap:');
});

test('marketing pages are indexable with a share image and product schema', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertDontSee('noindex')
        ->assertSee('<meta property="og:image" content="'.asset('images/og-image.png').'">', escape: false)
        ->assertSee('"@type":"SoftwareApplication"', escape: false)
        ->assertSee('"price":"'.number_format(ClubPlan::Starter->priceCents() / 100, 2, '.', '').'"', escape: false);
})->with([
    'home' => fn () => route('home'),
    'segment' => fn () => route('segments.show', ClubSegment::DanceSchool),
    'feature' => fn () => route('features.show', MarketingFeature::DirectDebit),
]);

test('account pages stay out of search results', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow" />', escape: false);
});

test('google analytics loads on marketing and app pages in production', function (string $url) {
    app()->detectEnvironment(fn () => 'production');

    $this->get($url)
        ->assertOk()
        ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-931HC5QPND', escape: false);
})->with([
    'marketing' => fn () => route('home'),
    'app' => fn () => route('login'),
]);

test('google analytics starts with every consent type denied and offers the cookie banner', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder([
            "gtag('consent', 'default'",
            "ad_storage: 'denied'",
            "ad_user_data: 'denied'",
            "ad_personalization: 'denied'",
            "gtag('config'",
            'googletagmanager.com/gtag/js',
        ], escape: false)
        ->assertSee('id="cookie-banner-template"', escape: false)
        ->assertSee('data-cookie-choice="denied"', escape: false)
        ->assertSee('data-cookie-choice="granted"', escape: false)
        ->assertSee('data-cookie-settings', escape: false);
});

test('google analytics and the cookie banner stay off outside production', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('googletagmanager.com', escape: false)
        ->assertDontSee('cookie-banner', escape: false)
        ->assertDontSee('data-cookie-settings', escape: false);
});
