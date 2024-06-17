<?php

namespace App\Models;

use App\Traits\GetUserTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpsJob extends Model
{
    use GetUserTimezone, HasFactory;

    protected $fillable = [
        'code',
        'created_by',
        'date',
        'delivered_by',
        'operator_id',
        'picked_at',
        'picked_by',
        'status',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'datetime',
        'picked_at' => 'datetime',
    ];

    // relationships
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function pickedBy()
    {
        return $this->belongsTo(User::class, 'picked_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

}
