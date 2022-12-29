<?php

namespace App\Models;

use App\Events\VendChannelSaved;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'qty',
        'capacity',
        'amount',
        'is_active',
        'vend_id',
        'product_id',
    ];

    // relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendChannelErrorLogs()
    {
        return $this->hasMany(VendChannelErrorLog::class)->latest();
    }

    public function vendChannelLatestError()
    {
        return $this->hasOne(VendChannelErrorLog::class)->orderByDesc('created_at');
    }
}
