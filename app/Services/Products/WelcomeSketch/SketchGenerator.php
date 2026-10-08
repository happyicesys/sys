<?php

namespace App\Services\Products\WelcomeSketch;

/**
 * Redraws a product photo as a welcome-scene sketch. One implementation per image provider;
 * bound in AppServiceProvider.
 */
interface SketchGenerator
{
    /** False until the provider is configured; nothing is sent then. */
    public function isConfigured(): bool;

    /** The model name recorded on the sketch row. */
    public function model(): string;

    /**
     * @param  string  $photo  the product photo's bytes
     * @param  list<string>  $styleReferences  bytes of approved sketches to copy the style from
     * @return string the sketch's image bytes (transparent background)
     *
     * @throws SketchGenerationException
     */
    public function generate(string $photo, string $productName, array $styleReferences): string;
}
