<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'remarks',
        'desc',
        'is_inventory',
    ];

    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->orderBy('sequence');
    }

    public function categories()
    {
        return $this->morphMany(Category::class, 'modelable')->orderBy('sequence');
    }

    public function unitCosts()
    {
        return $this->hasMany(UnitCost::class);
    }
}
