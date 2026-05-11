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
        // 'product_mapping_id',
        // 'vend_config_id',
    ];

    // relationships
    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function productMappings()
    {
        return $this->belongsToMany(ProductMapping::class)->orderBy('name', 'asc');
    }

    public function vends()
    {
        return $this->hasMany(Vend::class);
    }

    public function vendConfigs() : BelongsToMany
    {
        return $this->belongsToMany(VendConfig::class);
    }

    // scopes
    /**
     * Limit prefixes to those that have at least one Active machine
     * (is_active = true AND is_testing = false).
     *
     * Used by filter dropdowns (Customer View, Transaction, Report, etc.)
     * so the dropdown doesn't list prefixes whose machines are all
     * inactive/testing/disposed. Management pages (VendPrefix CRUD,
     * Settings, VendConfig, etc.) should NOT use this scope — they need
     * to see every prefix regardless of machine status.
     */
    public function scopeHasActiveVends($query)
    {
        return $query->whereHas('vends', function ($q) {
            $q->where('vends.is_active', true)
              ->where('vends.is_testing', false);
        });
    }
}
