<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendSerialNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'desc',
    ];

    public function vend()
    {
        return $this->hasOne(Vend::class);
    }
}
