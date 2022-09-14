<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileTax extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_id',
        'sequence',
        'tax_id',
        'is_inclusive',
    ];

    // relationships
    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class);
    }
}
