<?php

use App\Services\PersonName;

test('a full name is split into first name, prefix and last name', function (string $fullName, string $first, ?string $prefix, string $last) {
    expect(PersonName::split($fullName))->toBe(['first_name' => $first, 'name_prefix' => $prefix, 'last_name' => $last]);
})->with([
    'plain' => ['Anna Bakker', 'Anna', null, 'Bakker'],
    'prefix' => ['Sanne de Vries', 'Sanne', 'de', 'Vries'],
    'multi-word prefix' => ['Jan van der Berg', 'Jan', 'van der', 'Berg'],
    'double first name' => ['Jan Willem van den Heuvel', 'Jan Willem', 'van den', 'Heuvel'],
    'no prefix, double last name' => ['Jan Willem Jansen', 'Jan', null, 'Willem Jansen'],
    'single word' => ['Cher', 'Cher', null, ''],
    'extra spaces' => ['  Lisa   van   Dijk ', 'Lisa', 'van', 'Dijk'],
    'prefix word as last name' => ['Anna Van', 'Anna', null, 'Van'],
    'empty' => ['', '', null, ''],
]);

test('a name is composed from its parts, skipping empty ones', function () {
    expect(PersonName::compose('Jan', 'van der', 'Berg'))->toBe('Jan van der Berg')
        ->and(PersonName::compose('Anna', null, 'Bakker'))->toBe('Anna Bakker')
        ->and(PersonName::compose('Anna', '  ', 'Bakker'))->toBe('Anna Bakker')
        ->and(PersonName::fullLastName('van der', 'Berg'))->toBe('van der Berg');
});
