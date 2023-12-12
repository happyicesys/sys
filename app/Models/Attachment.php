<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'local_url',
        'modelable_id',
        'modelable_type',
        'full_url',
        'is_active',
        'type',
        'sequence',
        'name',
        'desc',
    ];

    // relationships
    public function modelable()
    {
        return $this->morphTo();
    }
}
