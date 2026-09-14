<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * @return list<string>
 */
function fluentIconNames(): array
{
    return collect(File::files(resource_path('views/flux/icon')))
        ->filter(fn ($file) => Str::contains($file->getContents(), 'fluentui-'))
        ->map(fn ($file) => Str::before($file->getFilename(), '.blade.php'))
        ->values()
        ->all();
}

test('every Flux icon override renders a Fluent SVG in all variants', function () {
    $names = fluentIconNames();

    expect($names)->not->toBeEmpty();

    foreach ($names as $name) {
        foreach (['outline', 'solid', 'mini', 'micro'] as $variant) {
            $rendered = Blade::render("<flux:icon.{$name} variant=\"{$variant}\" />");

            expect($rendered)->toContain('<svg', 'data-flux-icon', 'fill=')
                ->and($rendered)->not->toContain('stroke="currentColor"');
        }
    }
});

test('icons used in views all have a Fluent override', function () {
    $used = collect(File::allFiles(resource_path('views')))
        ->reject(fn ($file) => Str::startsWith($file->getRelativePathname(), 'flux/icon/'))
        ->flatMap(function ($file) {
            preg_match_all(
                '/<flux:icon\.([a-z0-9-]+)|icon(?::trailing)?="([a-z0-9-]+)"|\'icon\' => \'([a-z0-9-]+)\'/',
                $file->getContents(),
                $matches,
            );

            return array_filter(array_merge($matches[1], $matches[2], $matches[3]));
        })
        ->unique()
        // Flux's own animated spinner stays in place.
        ->reject(fn (string $name) => $name === 'loading')
        ->values();

    expect($used)->not->toBeEmpty()
        ->and($used->diff(fluentIconNames())->all())->toBe([]);
});
