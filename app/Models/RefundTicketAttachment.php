<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundTicketAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_ticket_id',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function ticket()
    {
        return $this->belongsTo(RefundTicket::class, 'refund_ticket_id');
    }
}
