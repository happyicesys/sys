<?php

namespace App\Services\SmartFreezer\Claude;

/** Counts what left a freezer in one door session from still frames. ClaudeFreezerCounter in production. */
interface FreezerCounter
{
    /**
     * Unusual things a customer did (Brian, 2026-11-15: "scan for abnormalities, like they're taking something, that
     * they put something back … or they trying to hide another sku when taking"). Each one Claude reports is also a
     * reason, and any of them sends the session to a person.
     */
    public const UNUSUAL = [
        'put_back', 'swapped_product', 'hidden_item', 'moved_between_sections', 'item_put_in', 'opened_pack',
    ];

    public const REASONS = [
        'hand_hidden', 'frost_or_glare', 'two_people', 'several_in_one_hand', 'video_missing',
        'video_cut_short', 'look_alike_products', 'not_in_catalog', 'misplaced_product', 'other',
        ...self::UNUSUAL,
    ];

    public function isConfigured(): bool;

    public function model(): string;

    /**
     * @param  list<DoorFrame>  $frames  in camera, then time order
     * @param  list<CatalogProduct>  $catalog  the freezer's live planogram
     * @param  list<WorkedExample>  $examples  earlier sessions with their confirmed count, sent first
     * @param  bool  $cacheAll  cache the whole request: the session will be counted again
     *
     * @throws CountFailed
     */
    public function count(array $frames, array $catalog, string $sessionLabel, array $examples = [], bool $cacheAll = false): FreezerCount;
}
