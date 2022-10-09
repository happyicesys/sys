<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'desc',
    ];

    public function tagBindings()
    {
        return $this->hasMany(TagBinding::class);
    }
}
