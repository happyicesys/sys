<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One machine's end-of-day state for a given day. See the
 * create_ops_machine_daily_snapshots_table migration for the rationale.
 */
class OpsMachineDailySnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'snapshot_date',
        'vend_id',
        'vend_code',
        'operator_id',
        'vend_prefix_id',
        'vend_model_id',
        'customer_id',
        'location_type_id',
        'category_id',
        'category_group_id',
        'is_active',
        'is_testing',
        'lcd_monitor_id',
        'bill_stat',
        'coin_stat',
        'card_terminal_id',
        'card_terminal_name',
        'firmware_ver',
        'apk_ver',
        'acb_rev',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'is_active' => 'boolean',
        'is_testing' => 'boolean',
    ];
}
