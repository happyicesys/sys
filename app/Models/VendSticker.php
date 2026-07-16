<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VendSticker extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'desc',
    ];

    public function vends(): BelongsToMany
    {
        return $this->belongsToMany(Vend::class, 'vend_sticker_vend');
    }
}
