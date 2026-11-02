<?php

namespace App\Services;

/**
 * Splits and composes Dutch-style names: first name(s), an optional prefix
 * ("tussenvoegsel": van, de, van der, ...) and a last name.
 */
class PersonName
{
    /**
     * Words that make up a name prefix, lowercase.
     *
     * @var list<string>
     */
    protected const array PREFIX_WORDS = [
        'van', 'de', 'der', 'den', 'het', "'t", 't', 'te', 'ten', 'ter', 'in', 'op', 'aan', 'bij', 'uit', 'onder', 'over', 'voor',
        'la', 'le', 'les', 'du', 'da', 'di', 'del', 'della', 'des', 'dos', 'das', "d'", "l'", 'von', 'zu', 'vom', 'zum', 'zur', 'van\'t',
    ];

    /**
     * Split a full name. The prefix is the first run of prefix words after
     * the first name, as long as a last name follows it: "Jan Willem van der
     * Berg" gives "Jan Willem", "van der", "Berg". Without a prefix the first
     * word is the first name and the rest the last name.
     *
     * @return array{first_name: string, name_prefix: string|null, last_name: string}
     */
    public static function split(string $fullName): array
    {
        $words = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === []) {
            return ['first_name' => '', 'name_prefix' => null, 'last_name' => ''];
        }

        for ($start = 1; $start < count($words) - 1; $start++) {
            if (! self::isPrefixWord($words[$start])) {
                continue;
            }

            $end = $start;

            while ($end + 1 < count($words) - 1 && self::isPrefixWord($words[$end + 1])) {
                $end++;
            }

            return [
                'first_name' => implode(' ', array_slice($words, 0, $start)),
                'name_prefix' => implode(' ', array_slice($words, $start, $end - $start + 1)),
                'last_name' => implode(' ', array_slice($words, $end + 1)),
            ];
        }

        return [
            'first_name' => $words[0],
            'name_prefix' => null,
            'last_name' => implode(' ', array_slice($words, 1)),
        ];
    }

    /**
     * The full name as it is shown: "Jan van der Berg".
     */
    public static function compose(?string $firstName, ?string $namePrefix, ?string $lastName): string
    {
        return implode(' ', array_filter(
            [trim((string) $firstName), trim((string) $namePrefix), trim((string) $lastName)],
            fn (string $part): bool => $part !== '',
        ));
    }

    /**
     * The last name including its prefix, as systems without a prefix field
     * (such as Moneybird) expect it: "van der Berg".
     */
    public static function fullLastName(?string $namePrefix, ?string $lastName): string
    {
        return self::compose(null, $namePrefix, $lastName);
    }

    protected static function isPrefixWord(string $word): bool
    {
        return in_array(mb_strtolower($word), self::PREFIX_WORDS, true);
    }
}
