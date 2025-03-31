<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'desc'
    ];

    public function vends()
    {
        return $this->hasMany(Vend::class);
    }
}
