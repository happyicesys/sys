<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductStockCountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $src = $this->resource; // stdClass/model

        $updated = data_get($src, 'last_updated') ?? data_get($src, 'updated_at');

        return [
            // identity
            'product_id'   => (int) (data_get($src, 'product_id') ?? 0),
            'product_code' => data_get($src, 'product_code'),
            'product_name' => data_get($src, 'product.name') ?? data_get($src, 'product_name'),

            // D0
            'unit_cost_d0'      => (float) (data_get($src, 'unit_cost_d0') ?? 0),
            'qty_vend_d0'       => (int) (data_get($src, 'qty_vend_d0') ?? 0),
            'qty_warehouse_d0'  => (int) (data_get($src, 'qty_warehouse_d0') ?? 0),
            'stock_value_d0'    => (float) (data_get($src, 'stock_value_d0') ?? 0), // already /100 & rounded in SQL
            'stock_cost_d0'     => (float) (data_get($src, 'stock_cost_d0') ?? 0),

            // D1
            'unit_cost_d1'      => (float) (data_get($src, 'unit_cost_d1') ?? 0),
            'qty_vend_d1'       => (int) (data_get($src, 'qty_vend_d1') ?? 0),
            'qty_warehouse_d1'  => (int) (data_get($src, 'qty_warehouse_d1') ?? 0),
            'stock_value_d1'    => (float) (data_get($src, 'stock_value_d1') ?? 0),
            'stock_cost_d1'     => (float) (data_get($src, 'stock_cost_d1') ?? 0),

            // D2
            'unit_cost_d2'      => (float) (data_get($src, 'unit_cost_d2') ?? 0),
            'qty_vend_d2'       => (int) (data_get($src, 'qty_vend_d2') ?? 0),
            'qty_warehouse_d2'  => (int) (data_get($src, 'qty_warehouse_d2') ?? 0),
            'stock_value_d2'    => (float) (data_get($src, 'stock_value_d2') ?? 0),
            'stock_cost_d2'     => (float) (data_get($src, 'stock_cost_d2') ?? 0),

            // optional rollups if you ever add them
            'stock_count'       => (int) (data_get($src, 'stock_count') ?? 0),

            'last_updated' => is_object($updated) && method_exists($updated, 'toDateTimeString')
                ? $updated->toDateTimeString()
                : ($updated ?: null),
        ];
    }
}
