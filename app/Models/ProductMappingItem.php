<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMappingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_code',
        'product_id',
        'product_mapping_id',
    ];
}
