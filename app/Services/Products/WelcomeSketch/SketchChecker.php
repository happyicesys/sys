<?php

namespace App\Services\Products\WelcomeSketch;

/**
 * Compares a finished welcome sketch with the photo it was drawn from. Advice for a person only:
 * nothing it answers changes which drawing is live. Bound in AppServiceProvider.
 */
interface SketchChecker
{
    public const INVENTED_TEXT = 'invented_text';

    public const MISSPELLED_TEXT = 'misspelled_text';

    public const PACKAGING = 'packaging';

    public const WRONG_PRODUCT = 'wrong_product';

    public const EXTRA_OBJECTS = 'extra_objects';

    public const FAULT_TYPES = [
        self::INVENTED_TEXT, self::MISSPELLED_TEXT, self::PACKAGING, self::WRONG_PRODUCT, self::EXTRA_OBJECTS,
    ];

    /** False until the provider is configured; nothing is sent then. */
    public function isConfigured(): bool;

    /** The model name recorded on the album entry. */
    public function model(): string;

    /**
     * @param  string  $photo  the product photo's bytes
     * @param  string  $drawing  the drawing's bytes
     * @param  string|null  $instructions  a person's instructions for this drawing; they outrank the brief
     * @return list<array{type: string, detail: string}> the faults found; empty = none
     *
     * @throws SketchGenerationException
     */
    public function check(string $photo, string $drawing, string $productName, ?string $instructions = null): array;
}
