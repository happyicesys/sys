<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'desc',
        'name',
    ];

    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->oldest();
    }

    public function vendPrefixes()
    {
        return $this->hasMany(VendPrefix::class);
    }
}
