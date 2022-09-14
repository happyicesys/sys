<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TagBinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'tag_id',
    ];

    // relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }
}
