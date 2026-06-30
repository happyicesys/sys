<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A cluster of physically co-located Sites (customers). See migration
 * 2026_06_29_000000_create_customer_groups_table for the rationale.
 *
 * Membership is exclusive (customers.customer_group_id). Use Customer::siblings()
 * to fetch a site's group-mates; consumers that want "travel together" behaviour
 * (e.g. the Ops Dashboard grouped-override filter) expand a matched customer set
 * by these memberships at read time.
 */
class CustomerGroup extends Model
{
    protected $fillable = [
        'name',
        'operator_id',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }
}
