<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single coin-float change event for a machine. Written inline by the VENDER
 * ingest job (App\Jobs\Vend\SyncVendParameter) only when CoinCnt changes while
 * the coin acceptor is active. See the create migration for the full contract.
 *
 * Amounts are raw (same base unit as vends.parameter_json.CoinCnt); divide by
 * 10^currency_exponent for display.
 */
class VendCoinFloatLog extends Model
{
    protected $table = 'vend_coin_float_logs';

    // Only created_at is kept (no updated_at) — set explicitly on insert.
    public $timestamps = false;

    protected $fillable = [
        'vend_id',
        'vend_code',
        'coin_cnt',
        'prev_coin_cnt',
        'delta',
        'coin_stat',
        'created_at',
    ];

    protected $casts = [
        'coin_cnt' => 'integer',
        'prev_coin_cnt' => 'integer',
        'delta' => 'integer',
        'coin_stat' => 'integer',
        'created_at' => 'datetime',
    ];

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }
}
