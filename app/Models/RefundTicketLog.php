<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundTicketLog extends Model
{
    use HasFactory;

    public $timestamps = true;
    const UPDATED_AT = null;   // only created_at is maintained

    protected $fillable = [
        'refund_ticket_id',
        'actor_id',
        'actor_label',
        'action',
        'from_status',
        'to_status',
        'note',
        'meta',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'meta' => 'array',
    ];

    public function ticket()
    {
        return $this->belongsTo(RefundTicket::class, 'refund_ticket_id');
    }
}
