<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TagBinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'modelable_id',
        'modelable_type',
        'tag_id',
    ];

    // relationships

    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }

    public function modelable()
    {
        return $this->morphTo();
    }
}
