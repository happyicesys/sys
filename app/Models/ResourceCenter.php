<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResourceCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'sequence',
        'name',
        'remarks',
    ];

    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->orderBy('sequence');
    }
}
