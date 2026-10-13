<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One product line of a CityboxOrder; replaced as a whole whenever the order changes. */
class CityboxOrderItem extends Model
{
    protected $fillable = [
        'order_id', 'citybox_product_id', 'product_id', 'product_name', 'price_cents', 'qty', 'total_cents',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'qty' => 'integer',
        'total_cents' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CityboxOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
