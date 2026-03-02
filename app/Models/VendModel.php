<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'desc',
        'is_sortable'
    ];

    public function vends()
    {
        return $this->hasMany(Vend::class);
    }
}
