<?php

namespace App\Services\SmartFreezer\Claude;

use App\Models\SmartFreezerRecognition;
use Throwable;

/**
 * A labelled map of a freezer's sections, sent to Claude with the session (Brian, 2026-11-14: "the planogram … already
 * exists in each of the smart freezer vends"). Claude is told each product's section ("21"); this shows it WHERE section
 * 21 is in camera 3's picture: the session's own BEFORE close-up with every section's code written on it.
 *
 * Where each basket sits is measured once per camera position (CameraPosition `baskets`, shares of the upright full
 * picture: the basket's left and right end and its row's height, section 1 at the right end — 50001 and 50002 alike,
 * 2026-11-14, from sessions that took one known product). How a basket is split comes from the session's own catalog,
 * so a basket remapped from three sections to two is labelled as two. A session at a time with no measured position
 * gets no map.
 */
final class SectionMap
{
    /**
     * @param  list<DoorFrame>  $frames
     * @param  list<CatalogProduct>  $catalog
     */
    public static function for(SmartFreezerRecognition $recognition, array $frames, array $catalog): ?DoorFrame
    {
        $config = (array) config('smart_freezer.claude_recognition.section_map');
        if (! (bool) ($config['enabled'] ?? false) || ! function_exists('imagettftext')) {
            return null;
        }
        $camera = 3;
        $layout = CameraPosition::for((string) $recognition->vend?->code, $recognition->created_at?->toDateTimeString(), $camera);
        if ($layout === null || empty($layout['baskets'])) {
            return null;
        }
        $before = collect($frames)->first(fn (DoorFrame $f) => $f->kind === DoorFrame::KIND_BEFORE && $f->camera === $camera);
        $box = ((array) config('smart_freezer.claude_recognition.closeups.box'))[$camera] ?? null;
        $font = collect((array) ($config['fonts'] ?? []))->first(fn ($path) => is_string($path) && is_file($path));
        if ($before === null || ! is_array($box) || count($box) !== 4 || $font === null) {
            return null;
        }

        $labels = self::labels((array) ($layout['baskets'] ?? []), self::divisions($catalog));
        if ($labels === []) {
            return null;
        }
        $jpeg = self::draw($before->jpeg, $labels, array_map('floatval', $box), $font);

        return $jpeg === null ? null : new DoorFrame($camera, $before->atMs, $before->videoMs, $jpeg, DoorFrame::KIND_MAP, ['sections' => count($labels)]);
    }

    /**
     * How many sections each basket has in this session's catalog: the highest section number seen ("23" = basket 2
     * has at least three). A code that is not basket 1-6 + section 1-9 is ignored.
     *
     * @param  list<CatalogProduct>  $catalog
     * @return array<int, int> basket => sections
     */
    public static function divisions(array $catalog): array
    {
        $baskets = [];
        foreach ($catalog as $product) {
            foreach ($product->sections as $code) {
                if (preg_match('/^([1-6])([1-9])$/', (string) $code, $m) === 1) {
                    $baskets[(int) $m[1]] = max($baskets[(int) $m[1]] ?? 0, (int) $m[2]);
                }
            }
        }
        ksort($baskets);

        return $baskets;
    }

    /**
     * Each section's code at its centre, as shares of the full picture: a basket's width split evenly, section 1 at
     * its right end.
     *
     * @param  array<int|string, array{0: float, 1: float, 2: float}>  $baskets  basket => [left, right, y]
     * @param  array<int, int>  $divisions
     * @return list<array{code: string, x: float, y: float}>
     */
    public static function labels(array $baskets, array $divisions): array
    {
        $labels = [];
        foreach ($divisions as $basket => $count) {
            $span = $baskets[$basket] ?? null;
            if (! is_array($span) || count($span) !== 3 || $count < 1) {
                continue;
            }
            [$left, $right, $y] = array_map('floatval', array_values($span));
            $width = ($right - $left) / $count;
            for ($section = 1; $section <= $count; $section++) {
                $labels[] = ['code' => $basket.$section, 'x' => $right - ($section - 0.5) * $width, 'y' => $y];
            }
        }

        return $labels;
    }

    /**
     * @param  list<array{code: string, x: float, y: float}>  $labels
     * @param  array{0: float, 1: float, 2: float, 3: float}  $box  the close-up's crop of the full picture
     */
    private static function draw(string $jpeg, array $labels, array $box, string $font): ?string
    {
        try {
            $image = @imagecreatefromstring($jpeg);
            if ($image === false) {
                return null;
            }
            $width = imagesx($image);
            $height = imagesy($image);
            [$boxLeft, $boxTop, $boxRight, $boxBottom] = $box;
            $size = max(12, (int) round($width / 56));
            $pad = (int) round($size * 0.35);
            $ink = imagecolorallocate($image, 255, 255, 255);
            $back = imagecolorallocatealpha($image, 20, 20, 160, 25);
            imagealphablending($image, true);
            foreach ($labels as $label) {
                $x = ($label['x'] - $boxLeft) / ($boxRight - $boxLeft) * $width;
                $y = ($label['y'] - $boxTop) / ($boxBottom - $boxTop) * $height;
                $bounds = imagettfbbox($size, 0, $font, $label['code']);
                $textWidth = $bounds[2] - $bounds[0];
                $textHeight = $bounds[1] - $bounds[7];
                $x0 = (int) round($x - $textWidth / 2);
                $y0 = (int) round($y + $textHeight / 2);
                imagefilledrectangle($image, $x0 - $pad, $y0 - $textHeight - $pad, $x0 + $textWidth + $pad, $y0 + $pad, $back);
                imagettftext($image, $size, 0, $x0, $y0, $ink, $font, $label['code']);
            }
            ob_start();
            imagejpeg($image, null, 85);
            $out = (string) ob_get_clean();
            imagedestroy($image);

            return $out === '' ? null : $out;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
