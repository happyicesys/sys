<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A payout group owns ONE originating bank account shared by several operators
 * (see database/migrations/..._create_payout_groups_table.php). Refund
 * settlements are bucketed per payout group so a settlement always exports to a
 * single CIMB file with one header account.
 */
class PayoutGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'bank_id',
        'bank_account_no',
        'bank_account_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function operators()
    {
        return $this->hasMany(Operator::class, 'payout_group_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }
}
