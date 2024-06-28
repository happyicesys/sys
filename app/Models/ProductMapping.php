<?php

namespace App\Models;

use App\Models\Scopes\OperatorProductFilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMapping extends Model
{
    use HasFactory;

    // const ATTACHMENT_PRICE_TYPE = [
    //     11 => 'P1',
    //     12 => 'P2',
    //     13 => 'P3',
    //     14 => 'P4',
    //     15 => 'P5',
    // ];

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
        'is_active',
        'operator_id',
        'product_mapping_items_json',
        'vends_json',
    ];


    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->oldest();
    }

    // public function attachmentP1()
    // {
    //     return $this->morphMany(Attachment::class, 'modelable')
    //             ->where('type', )
    //             ->orderBy('sequence');
    // }

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

    public function vendPrefixes()
    {
        return $this->belongsToMany(VendPrefix::class);
    }
}
