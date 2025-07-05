<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportJob extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'status',
        'filename',
        'error_message',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachment()
    {
        return $this->morphOne(Attachment::class, 'modelable');
    }
}
