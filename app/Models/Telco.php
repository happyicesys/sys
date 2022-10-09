<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Telco extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'remarks',
    ];

    // relationships
    public function simcards()
    {
        return $this->hasMany(Simcard::class);
    }
}
