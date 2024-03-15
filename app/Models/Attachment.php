<?php

namespace App\Models;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'local_url',
        'modelable_id',
        'modelable_type',
        'full_url',
        'is_active',
        'type',
        'sequence',
        'name',
        'desc',
    ];

    // protected function createdAt(): Attribute
    // {
    //     return Attribute::make(
    //         get: fn (string $value) => Carbon::parse($value)->format('ymd h:i a'),
    //     );
    // }

    // relationships
    public function modelable()
    {
        return $this->morphTo();
    }
}
