<?php

namespace App\Services\Citybox\DTO;

/**
 * One row of `product_list` (merchant catalog): identity, images and their
 * enabled/disabled `status`.
 *
 * `status` is UNDOCUMENTED — absent from their 2026-08-14 spec, present live
 * since (confirmed 2026-09-05). It is the only signal that a SKU is retired:
 * their catalog returns disabled rows forever, so absence never fires. Kept
 * as their raw number so an unfamiliar value stays visible; 0 = disabled,
 * 1 = enabled, 99 seen once and unconfirmed. Anything that is not 1 counts
 * as not-enabled, which is the safe reading for a value we cannot explain.
 */
final readonly class ChillerCatalogItem
{
    public const STATUS_DISABLED = 0;

    public const STATUS_ENABLED = 1;

    public function __construct(
        public int $cityboxProductId,
        public string $name,
        public ?string $skuCode,     // their product_id field — "0"/empty for us today; keep in case they populate it
        public ?string $imgUrl,
        /** @var string[] */
        public array $visionImgs,
        public ?int $status = null,  // their `status`; null when they omit it
    ) {}

    /** True only for their explicit enabled value. Unknown ⇒ not enabled. */
    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /** A value we have no meaning for — worth a log line, never a silent guess. */
    public function hasUnknownStatus(): bool
    {
        return $this->status !== null
            && $this->status !== self::STATUS_ENABLED
            && $this->status !== self::STATUS_DISABLED;
    }

    public static function fromApi(array $r): self
    {
        $sku = isset($r['product_id']) ? (string) $r['product_id'] : null;

        return new self(
            cityboxProductId: (int) ($r['id'] ?? 0),
            name: (string) ($r['product_name'] ?? ''),
            skuCode: ($sku === null || $sku === '' || $sku === '0') ? null : $sku,
            imgUrl: isset($r['img_url']) && $r['img_url'] !== '' ? (string) $r['img_url'] : null,
            visionImgs: array_values(array_filter([
                $r['vision_img'] ?? null, $r['vision_img2'] ?? null,
                $r['vision_img3'] ?? null, $r['vision_img4'] ?? null,
            ], fn ($u) => is_string($u) && $u !== '')),
            status: isset($r['status']) && is_numeric($r['status']) ? (int) $r['status'] : null,
        );
    }
}
