<?php

namespace App\Services\Grab\Menu;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * One product on a machine's Grab menu: the slots holding it, what they can sell now,
 * and its Grab price and sub-category. Listed on Grab only when it has both.
 */
final class MachineMenuLine
{
    // Why a product on the machine is not on its Grab menu.
    const NO_SUB_CATEGORY = 'no_sub_category';

    /**
     * @param  Collection<int, array{vend_channel_id:int, code:string, available:int}>  $slots  most available first
     */
    public function __construct(
        public readonly Product $product,
        public readonly Collection $slots,
        public readonly ?int $price,
        public readonly ?int $rp,
        public readonly ?array $subCategory,
        public readonly ?string $priceProblem,
    ) {}

    public function itemId(): string
    {
        return GrabMenuItemId::forProduct($this->product->id);
    }

    public function available(): int
    {
        return (int) $this->slots->sum('available');
    }

    public function isListed(): bool
    {
        return $this->problem() === null;
    }

    // no_rp / no_custom_price / no_sub_category, or null when listed.
    public function problem(): ?string
    {
        if ($this->priceProblem !== null) {
            return $this->priceProblem;
        }

        return empty($this->subCategory['id']) ? self::NO_SUB_CATEGORY : null;
    }
}
