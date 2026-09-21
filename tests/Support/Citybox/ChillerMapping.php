<?php

namespace Tests\Support\Citybox;

use App\Models\CityboxProduct;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductMappingItem;
use App\Models\Vend;

/**
 * Build a chiller planogram the way ops now do: our mapping decides the codes,
 * the SKU decides capacity. Each spec row is
 *     <channel code> => [<citybox product id>, <chiller_slot_qty>]
 * and the mark1 product + CityBox link are created on demand.
 */
class ChillerMapping
{
    /** @param  array<int,array{0:int,1:int}>  $spec */
    public static function bind(Vend $vend, array $spec, string $name = 'Chiller layout A'): ProductMapping
    {
        $mapping = ProductMapping::create([
            'name' => $name,
            'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER,
            'is_smart' => false,
            'is_active' => true,
            'operator_id' => $vend->operator_id ?: 1,
        ]);

        foreach ($spec as $code => [$cityboxProductId, $capacity]) {
            ProductMappingItem::create([
                'product_mapping_id' => $mapping->id,
                'channel_code' => (string) $code,
                'product_id' => self::product($cityboxProductId, $capacity)->id,
                'sequence' => $code,
            ]);
        }

        $vend->forceFill(['product_mapping_id' => $mapping->id])->save();

        return $mapping;
    }

    /** A mark1 product linked to a CityBox SKU, carrying its chiller capacity. */
    public static function product(int $cityboxProductId, ?int $capacity = null): Product
    {
        $product = Product::withoutGlobalScopes()->firstOrCreate(
            ['code' => (string) $cityboxProductId],
            ['name' => 'CB '.$cityboxProductId, 'is_active' => true, 'is_inventory' => true],
        );
        if ($capacity !== null) {
            $product->forceFill(['chiller_slot_qty' => $capacity])->save();
        }
        CityboxProduct::firstOrCreate(
            ['citybox_product_id' => $cityboxProductId],
            ['product_id' => $product->id, 'name' => 'CB '.$cityboxProductId, 'is_delisted' => false, 'first_seen_at' => now()],
        );

        return $product;
    }
}
