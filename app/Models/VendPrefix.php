<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendPrefix extends Model
{
    use HasFactory;

    protected $fillable = [
        'desc',
        'name',
        'vend_config_id',
    ];

    // relationships
    public function productMappings()
    {
        return $this->belongsToMany(ProductMapping::class, 'product_mapping_vend_prefix', 'vend_prefix_id', 'product_mapping_id');
    }

    public function vend()
    {
        return $this->hasMany(Vend::class);
    }

    public function vendConfig()
    {
        return $this->belongsTo(VendConfig::class);
    }
}
