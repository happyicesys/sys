<?php

namespace App\Models;

use App\Models\Scopes\OperatorProductFilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMapping extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::addGlobalScope(new OperatorProductFilterScope);
    }

    protected $casts = [
        'product_mapping_items_json' => 'json',
        'vends_json' => 'json',
    ];

    protected $fillable = [
        'name',
        'remarks',
        'operator_id',
        'product_mapping_items_json',
        'vends_json',
    ];


    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable');
    }

    public function productMappingItems()
    {
        return $this->hasMany(ProductMappingItem::class)->orderBy('channel_code', 'asc');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function vends()
    {
        return $this->hasMany(Vend::class)->orderBy('code');
    }
}
