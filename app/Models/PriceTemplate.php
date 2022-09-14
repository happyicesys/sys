<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'remarks',
    ];

    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function priceTemplateItems()
    {
        return $this->hasMany(PriceTemplateItem::class)->orderBy('sequence');
    }
}
