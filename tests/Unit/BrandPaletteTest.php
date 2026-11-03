<?php

use App\Services\BrandPalette;

/** WCAG contrast ratio of a hex colour against white. */
function contrastWithWhite(string $hex): float
{
    $luminance = collect(str_split(ltrim($hex, '#'), 2))
        ->map(fn (string $channel): float => hexdec($channel) / 255)
        ->map(fn (float $channel): float => $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4)
        ->pipe(fn ($channels): float => 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2]);

    return 1.05 / ($luminance + 0.05);
}

test('SquadPass blue reproduces its own 600 shade', function () {
    expect(BrandPalette::fromHex(BrandPalette::DEFAULT_COLOR)[600])->toBe(BrandPalette::DEFAULT_COLOR);
});

test('every brand colour yields a full palette that keeps white text readable on 600', function (string $brandColor) {
    $palette = BrandPalette::fromHex($brandColor);

    expect(array_keys($palette))->toBe([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950])
        ->and($palette)->each->toMatch('/^#[0-9a-f]{6}$/')
        ->and(contrastWithWhite($palette[600]))->toBeGreaterThanOrEqual(4.5);
})->with([
    'red' => '#e11d48',
    'yellow' => '#facc15',
    'light green' => '#86efac',
    'orange' => '#ff6600',
    'white' => '#ffffff',
    'black' => '#000000',
]);

test('only #rrggbb counts as a brand colour', function (?string $value, bool $valid) {
    expect(BrandPalette::isValidHex($value))->toBe($valid);
})->with([
    'hex' => ['#E11D48', true],
    'short hex' => ['#e14', false],
    'css injection' => ['#e11d48; } body { display: none', false],
    'empty' => [null, false],
]);
