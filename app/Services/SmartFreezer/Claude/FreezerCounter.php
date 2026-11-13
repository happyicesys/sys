<?php

namespace App\Services\SmartFreezer\Claude;

/** Counts what left a freezer in one door session from still frames. ClaudeFreezerCounter in production. */
interface FreezerCounter
{
    public const REASONS = [
        'hand_hidden', 'frost_or_glare', 'two_people', 'several_in_one_hand', 'video_missing',
        'video_cut_short', 'look_alike_products', 'not_in_catalog', 'other',
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
