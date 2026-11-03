<?php

namespace App\Services;

/**
 * Turns one brand colour into a full 50–950 palette that stands in for
 * Tailwind's blue. Each shade keeps the perceived lightness (OKLCH L) of the
 * blue shade it replaces and takes the hue and saturation of the brand colour,
 * so white text on a 600 button stays as readable as on SquadPass blue, also
 * for light brand colours such as yellow.
 */
class BrandPalette
{
    public const string DEFAULT_COLOR = '#2563eb';

    /**
     * Tailwind 4's blue palette as OKLCH lightness and chroma.
     *
     * @var array<int, array{0: float, 1: float}>
     */
    private const array BLUE = [
        50 => [0.970, 0.014],
        100 => [0.932, 0.032],
        200 => [0.882, 0.059],
        300 => [0.809, 0.105],
        400 => [0.707, 0.165],
        500 => [0.623, 0.214],
        600 => [0.546, 0.245],
        700 => [0.488, 0.243],
        800 => [0.424, 0.199],
        900 => [0.379, 0.146],
        950 => [0.282, 0.091],
    ];

    /** Chroma of blue-600, the shade a brand colour is matched against. */
    private const float BLUE_600_CHROMA = 0.245;

    /** Brand colours more vivid than blue are not pushed further than this. */
    private const float MAX_CHROMA_SCALE = 1.2;

    /**
     * Hex colour per shade, keyed 50 to 950.
     *
     * @return array<int, string>
     */
    public static function fromHex(string $hex): array
    {
        [, $chroma, $hue] = self::hexToOklch($hex);
        $scale = min($chroma / self::BLUE_600_CHROMA, self::MAX_CHROMA_SCALE);

        $palette = [];

        foreach (self::BLUE as $shade => [$lightness, $blueChroma]) {
            $palette[$shade] = self::oklchToHex($lightness, $blueChroma * $scale, $hue);
        }

        return $palette;
    }

    public static function isValidHex(?string $hex): bool
    {
        return is_string($hex) && preg_match('/^#[0-9a-f]{6}$/i', $hex) === 1;
    }

    /**
     * @return array{0: float, 1: float, 2: float} lightness, chroma, hue in degrees
     */
    private static function hexToOklch(string $hex): array
    {
        [$red, $green, $blue] = array_map(
            fn (string $channel): float => self::toLinear(hexdec($channel) / 255),
            str_split(ltrim($hex, '#'), 2),
        );

        $long = (0.4122214708 * $red + 0.5363325363 * $green + 0.0514459929 * $blue) ** (1 / 3);
        $medium = (0.2119034982 * $red + 0.6806995451 * $green + 0.1073969566 * $blue) ** (1 / 3);
        $short = (0.0883024619 * $red + 0.2817188376 * $green + 0.6299787005 * $blue) ** (1 / 3);

        $lightness = 0.2104542553 * $long + 0.7936177850 * $medium - 0.0040720468 * $short;
        $a = 1.9779984951 * $long - 2.4285922050 * $medium + 0.4505937099 * $short;
        $b = 0.0259040371 * $long + 0.7827717662 * $medium - 0.8086757660 * $short;

        return [$lightness, sqrt($a ** 2 + $b ** 2), rad2deg(atan2($b, $a))];
    }

    /**
     * Converts to hex, lowering the chroma until the colour fits in sRGB so the
     * lightness, which carries the contrast, is never changed.
     */
    private static function oklchToHex(float $lightness, float $chroma, float $hue): string
    {
        $rgb = self::oklchToLinearRgb($lightness, $chroma, $hue);

        if (! self::inGamut($rgb)) {
            $low = 0.0;
            $high = $chroma;

            for ($step = 0; $step < 20; $step++) {
                $middle = ($low + $high) / 2;

                if (self::inGamut(self::oklchToLinearRgb($lightness, $middle, $hue))) {
                    $low = $middle;
                } else {
                    $high = $middle;
                }
            }

            $rgb = self::oklchToLinearRgb($lightness, $low, $hue);
        }

        return '#'.implode('', array_map(
            fn (float $channel): string => str_pad(dechex((int) round(self::fromLinear($channel) * 255)), 2, '0', STR_PAD_LEFT),
            $rgb,
        ));
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private static function oklchToLinearRgb(float $lightness, float $chroma, float $hue): array
    {
        $a = $chroma * cos(deg2rad($hue));
        $b = $chroma * sin(deg2rad($hue));

        $long = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $medium = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $short = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        return [
            4.0767416621 * $long - 3.3077115913 * $medium + 0.2309699292 * $short,
            -1.2684380046 * $long + 2.6097574011 * $medium - 0.3413193965 * $short,
            -0.0041960863 * $long - 0.7034186147 * $medium + 1.7076147010 * $short,
        ];
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $rgb
     */
    private static function inGamut(array $rgb): bool
    {
        foreach ($rgb as $channel) {
            if ($channel < -0.0001 || $channel > 1.0001) {
                return false;
            }
        }

        return true;
    }

    private static function toLinear(float $channel): float
    {
        return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }

    private static function fromLinear(float $channel): float
    {
        $channel = max(0.0, min(1.0, $channel));

        return $channel <= 0.0031308 ? $channel * 12.92 : 1.055 * $channel ** (1 / 2.4) - 0.055;
    }
}
