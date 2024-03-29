<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendChannelErrorLog extends Model
{
    use HasFactory;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'vend_channel_id',
        'vend_channel_error_id',
        'vend_transaction_id',
        'is_error_cleared',
    ];

    public function vendChannel()
    {
        return $this->belongsTo(VendChannel::class);
    }

    public function vendChannelError()
    {
        return $this->belongsTo(VendChannelError::class);
    }
}
