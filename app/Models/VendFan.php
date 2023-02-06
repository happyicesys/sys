<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendFan extends Model
{
    use HasFactory;

    const TYPE_MAIN = 1;

    protected $fillable = [
        'vend_id',
        'value',
        'type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // relationships
    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
