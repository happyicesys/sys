<?php

namespace App\Models;

use App\Events\VendChannelSaved;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendChannel extends Model
{
    use HasFactory;

    protected static function booted()
    {
        // save channels json to vend
        static::saved(function ($vendChannel) {
            $vendChannel->vend()->update([
                'vend_channels_json' => $vendChannel->vend->vendChannels,
            ]);
        });
    }

    protected $fillable = [
        'code',
        'qty',
        'capacity',
        'amount',
        'is_active',
        'vend_id',
    ];

    // relationships
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
