<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'desc',
        'rate',
        'is_active',
    ];

    public function tax()
    {
        return $this->hasMany(ProfileTax::class)->orderBy('sequence');
    }
}
