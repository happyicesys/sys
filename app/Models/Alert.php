<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'resolved_at' => 'datetime',
        'ai_analysis' => 'array',
    ];

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
