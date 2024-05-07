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
        'operator_id'
    ];

    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->oldest();
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function vendPrefixes()
    {
        return $this->hasMany(VendPrefix::class);
    }
}
