<?php

namespace App\Services\Products\WelcomeSketch;

/**
 * Removes a product photo's background (the free, open-source fallback when no image model can
 * draw the sketch). Bound in AppServiceProvider.
 */
interface CutoutMaker
{
    public function isAvailable(): bool;

    /** Recorded as the sketch's `model`. */
    public function name(): string;

    /**
     * @return string PNG bytes with a transparent background
     *
     * @throws SketchGenerationException
     */
    public function cutout(string $photo): string;
}
