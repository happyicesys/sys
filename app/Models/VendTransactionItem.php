<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendTransactionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_refunded',
        'product_id',
        'product_mapping_item_id',
        'unit_cost',
        'unit_cost_id',
        'unit_price_amount',
        'vend_channel_id',
        'vend_channel_code',
        'vend_channel_error_code',
        'vend_channel_error_id',
        'vend_transaction_id',
    ];

    // relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The exact planogram row (channel_code -> product -> selling_price) that
     * resolved this line of a multi-purchase transaction.
     */
    public function productMappingItem()
    {
        return $this->belongsTo(ProductMappingItem::class);
    }

    public function unitCost()
    {
        return $this->belongsTo(UnitCost::class);
    }

    public function vendChannel()
    {
        return $this->belongsTo(VendChannel::class);
    }

    public function vendChannelError()
    {
        return $this->belongsTo(VendChannelError::class);
    }

    public function vendTransaction()
    {
        return $this->belongsTo(VendTransaction::class);
    }
}
