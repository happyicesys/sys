<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundTicketItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_ticket_id',
        'vend_transaction_item_id',
        'product_id',
        'product_name',
        'product_sku',
        'vend_channel_code',
        'unit_price_cents',
        'had_channel_error',
        'vend_channel_error_code',
        'channel_error_desc',
        'channel_error_weightage',
        'item_recommendation',
        'approved',
    ];

    protected $casts = [
        'had_channel_error' => 'boolean',
        'approved' => 'boolean',
        'unit_price_cents' => 'integer',
        'channel_error_weightage' => 'integer',
    ];

    public function ticket()
    {
        return $this->belongsTo(RefundTicket::class, 'refund_ticket_id');
    }

    public function vendTransactionItem()
    {
        return $this->belongsTo(VendTransactionItem::class, 'vend_transaction_item_id');
    }
}
