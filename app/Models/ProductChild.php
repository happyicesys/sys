<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A real flavour bound under a blind parent product, with its weight.
 * See migration create_product_children_table.
 */
class ProductChild extends Model
{
    use HasFactory;

    protected $table = 'product_children';

    protected $fillable = [
        'parent_product_id',
        'child_product_id',
        'weight_pct',
        'sort',
        'is_active',
    ];

    protected $casts = [
        'weight_pct' => 'integer',
        'sort' => 'integer',
        'is_active' => 'boolean',
    ];

    public function parentProduct()
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    public function childProduct()
    {
        return $this->belongsTo(Product::class, 'child_product_id');
    }
}
