<?php

namespace App\Services\CheckIns;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use GdImage;

/**
 * Renders a check-in code as a QR code: inline SVG for the web portal, PNG for
 * the mobile app, which loads the member's own code as a plain image.
 *
 * Both carry the SquadPass mark in the middle, so a member sees whose code they
 * are holding up. That covers modules, which is why every code is encoded at
 * the highest error correction level: level H recovers ~30% of the symbol while
 * the mark plus its white pad takes up around 8%.
 */
class QrCodeRenderer
{
    /**
     * How much of the code's width the mark (including its pad) may cover.
     * The kiosk view sizes its overlay from this, so the two cannot drift.
     */
    public const LOGO_RATIO = 0.28;

    /**
     * The code as inline SVG. The XML prolog and the fixed pixel size are
     * stripped: inline in a page the viewBox should scale to its container,
     * which a `width`/`height` pair on the root would prevent.
     */
    public function svg(string $value, int $size = 400): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size, margin: 0),
            new SvgImageBackEnd,
        ));

        return (string) preg_replace(
            ['/^<\?xml[^?]*\?>\s*/', '/(<svg\b[^>]*?)\s+width="\d+"\s+height="\d+"/'],
            ['', '$1'],
            $writer->writeString($value, ecLevel: ErrorCorrectionLevel::H()),
        );
    }

    /**
     * A PNG of the same code, drawn straight from the encoded matrix with GD so
     * no image extension beyond GD itself is needed.
     */
    public function png(string $value, int $moduleSize = 8, int $quietZone = 2): string
    {
        $matrix = Encoder::encode($value, ErrorCorrectionLevel::H())->getMatrix();
        $size = ($matrix->getWidth() + $quietZone * 2) * $moduleSize;

        $image = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefilledrectangle($image, 0, 0, $size - 1, $size - 1, $white);

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }

                $left = ($x + $quietZone) * $moduleSize;
                $top = ($y + $quietZone) * $moduleSize;

                imagefilledrectangle($image, $left, $top, $left + $moduleSize - 1, $top + $moduleSize - 1, $black);
            }
        }

        $this->stampMark($image, $size, $moduleSize, $white);

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /**
     * Knock a white hole in the middle of the code and drop the mark into it.
     * The hole snaps to whole modules so the surrounding modules stay intact.
     */
    private function stampMark(GdImage $image, int $size, int $moduleSize, int $white): void
    {
        $mark = @imagecreatefrompng(public_path('images/logos/squadpass-mark.png'));

        if (! $mark) {
            return;
        }

        $modules = max(1, (int) round($size * self::LOGO_RATIO / $moduleSize));
        $hole = $modules * $moduleSize;
        $holeOffset = (int) (($size - $hole) / 2);

        imagefilledrectangle($image, $holeOffset, $holeOffset, $holeOffset + $hole - 1, $holeOffset + $hole - 1, $white);

        // One module of white padding all round keeps the mark clear of the code.
        $markSize = $hole - 2 * $moduleSize;
        $markOffset = (int) (($size - $markSize) / 2);

        imagealphablending($image, true);
        imagecopyresampled(
            $image,
            $mark,
            $markOffset,
            $markOffset,
            0,
            0,
            $markSize,
            $markSize,
            imagesx($mark),
            imagesy($mark),
        );
        imagedestroy($mark);
    }
}
