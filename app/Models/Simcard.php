<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Simcard extends Model
{
    use HasFactory;

    protected $fillable = [
        'begin_date',
        'code',
        'created_by',
        'is_active',
        'operator_id',
        'phone_number',
        'telco_id',
        'termination_date',
        'updated_by',
    ];

    // relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function telco()
    {
        return $this->belongsTo(Telco::class);
    }

    public function vend()
    {
        return $this->hasOne(Vend::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class);
    }

}
