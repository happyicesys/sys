<?php

namespace App\Models;

use App\Models\Scopes\OperatorIDFilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendPrefix extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::addGlobalScope(new OperatorIDFilterScope);
    }

    protected $fillable = [
        'desc',
        'name',
        'operator_id',
        'vend_config_id',
    ];

    // relationships
    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function productMappings()
    {
        return $this->belongsToMany(ProductMapping::class, 'product_mapping_vend_prefix', 'vend_prefix_id', 'product_mapping_id');
    }

    public function vends()
    {
        return $this->hasMany(Vend::class);
    }

    public function vendConfig()
    {
        return $this->belongsTo(VendConfig::class);
    }
}
