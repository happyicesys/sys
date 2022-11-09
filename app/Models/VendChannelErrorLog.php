<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendChannelErrorLog extends Model
{
    use HasFactory;

    protected static function booted()
    {
        // save channel error logs json to vend
        static::saved(function ($vendChannelErrorLog) {
            $vendChannelErrorLog->vendChannel->vend()->update([
                'vend_channel_error_logs_json' => VendChannelErrorLog::with(['vendChannel', 'vendChannelError'])->whereIn('vend_channel_id', $vendChannelErrorLog->vendChannel->vend->vendChannels->pluck('id'))->where('is_error_cleared', false)->get(),
            ]);
        });
    }

    protected $fillable = [
        'vend_channel_id',
        'vend_channel_error_id',
        'is_error_cleared'
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
