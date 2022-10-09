<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendType extends Model
{
    use HasFactory;

    protected $fillable = [
        'sequence',
        'name',
        'desc',
    ];

    // relationships
    public function vends()
    {
        return $this->hasMany(Vend::class);
    }
}
