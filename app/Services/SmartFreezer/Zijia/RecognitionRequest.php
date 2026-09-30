<?php

namespace App\Services\SmartFreezer\Zijia;

use InvalidArgumentException;

/**
 * One door session put to the algorithm: `dynamic.cabinet.add.queue` (§4.2).
 *
 * `goodsCodes` is the CANDIDATE set — every SKU the cabinet may hold, by product code — not what the
 * customer paid for. The algorithm picks what left the cabinet from that list and answers with
 * codes and counts, which mark1 then holds against the paid cart (RecognitionVerdict).
 */
final class RecognitionRequest
{
    /**
     * @param  list<string>  $videoUrls
     * @param  list<string>  $goodsCodes  product codes (`sn`), de-duplicated
     * @param  list<string>  $modelIds  may be empty: Zijia, 2026-09-30 — "modelIdList 可以先不用传"
     */
    public function __construct(
        public readonly string $deviceId,
        public readonly string $tradeId,
        public readonly array $videoUrls,
        public readonly array $goodsCodes,
        public readonly array $modelIds,
        public readonly string $notifyUrl,
        public readonly int $videoDuration = 0,
        public readonly int $doorId = 1,
    ) {
        foreach (['deviceId' => $deviceId, 'tradeId' => $tradeId, 'notifyUrl' => $notifyUrl] as $name => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("$name is required");
            }
        }
        // Their server dereferences goodsList without a null check (probe, 2026-09-27), and a
        // recognition with no video or no model has nothing to run on — refuse here, not there.
        foreach (['videoUrls' => $videoUrls, 'goodsCodes' => $goodsCodes] as $name => $list) {
            if ($list === []) {
                throw new InvalidArgumentException("$name must not be empty");
            }
        }
    }

    /** The `bizContent` object, before it is JSON-encoded into the envelope. */
    public function bizContent(): array
    {
        return [
            'modelIdList' => array_values($this->modelIds),
            'videoList' => array_values($this->videoUrls),
            'goodsList' => array_map(fn (string $sn) => ['positions' => [], 'sn' => $sn], array_values(array_unique($this->goodsCodes))),
            'notifyUrl' => $this->notifyUrl,
            'orderStatus' => 0,
            'deviceId' => $this->deviceId,
            'tradeId' => $this->tradeId,
            'doorId' => $this->doorId,
            'videoDuration' => $this->videoDuration,
        ];
    }
}
