<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMapping extends Model
{
    use HasFactory;

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
        return $this->hasMany(ProductMappingItem::class);
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
