<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryGroup extends Model
{
    use HasFactory;

    protected $fillable =[
        'classname',
        'desc',
        'name',
    ];

    // relationships
    public function categories()
    {
        return $this->hasMany(Category::class);
    }
}
