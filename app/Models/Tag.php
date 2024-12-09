<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'classname',
        'name',
        'slug',
        'desc',
    ];

    // mutator and accessor
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => strtolower(trim(preg_replace('/\s+/', '_', $value))),
        );
    }

    public function tagBindings()
    {
        return $this->hasMany(TagBinding::class);
    }
}
