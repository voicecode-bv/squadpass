<?php

namespace App\Http\Controllers;

use App\Enums\ClubSegment;
use App\Enums\MarketingFeature;
use Illuminate\Http\Response;

/**
 * Crawler entry points: robots.txt (served here rather than from public/ so it
 * can point at the absolute sitemap URL of each environment) and sitemap.xml.
 */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow:', '', 'Sitemap: '.route('sitemap')]
            // Test and staging environments stay out of search results altogether.
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Every public marketing page; app, auth and club pages carry noindex instead.
     */
    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0'],
            ...array_map(fn (ClubSegment $segment): array => [
                'loc' => route('segments.show', $segment),
                'priority' => '0.8',
            ], ClubSegment::cases()),
            ['loc' => route('features.index'), 'priority' => '0.8'],
            ...array_map(fn (MarketingFeature $feature): array => [
                'loc' => route('features.show', $feature),
                'priority' => '0.8',
            ], MarketingFeature::cases()),
            ['loc' => route('privacy'), 'priority' => '0.3'],
        ];

        // Built here rather than in Blade, where the closing tag of the XML declaration would end the compiled PHP.
        $entries = array_map(fn (array $url): string => sprintf(
            "  <url>\n    <loc>%s</loc>\n    <changefreq>weekly</changefreq>\n    <priority>%s</priority>\n  </url>",
            e($url['loc']),
            $url['priority'],
        ), $urls);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode("\n", $entries)."\n"
            .'</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
