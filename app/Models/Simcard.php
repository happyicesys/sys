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
        'msisdn',
        'operator_id',
        'phone_number',
        'telco_id',
        'termination_date',
        'updated_by',
    ];

    // usage_* is the telco-API snapshot written by simcards:sync-usage — never
    // user input, hence not fillable (the sync service forceFill()s it).
    protected $casts = [
        'usage_active_at' => 'datetime',
        'usage_expire_at' => 'datetime',
        'usage_synced_at' => 'datetime',
        'usage_used_mb' => 'float',
    ];

    // relationships

    // The foreign keys are named after the columns, NOT Eloquent's default:
    // belongsTo() infers created_by_id / updated_by_id from the method names,
    // and simcards has created_by / updated_by. Left to infer, the relation
    // reads a column that does not exist, resolves to null and the Index's
    // "Updated By" reads '—' for every row — which is exactly how it shipped.
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function telco()
    {
        return $this->belongsTo(Telco::class);
    }

    public function vends()
    {
        return $this->hasMany(Vend::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
