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
        'product_json',
        'unit_cost',
        'unit_cost_id',
        'vend_channel_id',
        'vend_channel_code',
        'vend_channel_error_id',
        'vend_transaction_id',
    ];

    // relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
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
