<?php

namespace App\Models;

use App\Models\Scopes\OperatorIDFilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'product_mapping_id',
        'vend_config_id',
    ];

    // relationships
    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function productMapping()
    {
        return $this->belongsTo(ProductMapping::class);
    }

    public function vends()
    {
        return $this->hasMany(Vend::class);
    }

    public function vendConfigs() : BelongsToMany
    {
        return $this->belongsToMany(VendConfig::class);
    }
}
