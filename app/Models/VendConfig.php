<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VendConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'desc',
        'is_active',
        'name',
        'operator_id'
    ];

    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->latest();
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    // public function compatibleVendConfigs()
    // {
    //     return $this->belongsToMany(VendConfig::class);
    // }

    public function vendPrefixes() : BelongsToMany
    {
        return $this->belongsToMany(VendPrefix::class);
    }
}
