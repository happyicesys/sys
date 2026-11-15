<?php

namespace App\Services\SmartFreezer\Claude;

/** One product on the freezer's live planogram, as Claude is shown it: id, name, sections and a small photo. */
final class CatalogProduct
{
    /** @param  list<string>  $sections  the channel codes it sits in ("21" = basket 2, section 1) */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $code,
        public readonly array $sections,
        public readonly ?string $photoJpeg,
        /** How many views of the pack the photo shows side by side (ProductViews): 1 = a single product photo. */
        public readonly int $views = 1,
    ) {}
}
