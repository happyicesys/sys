<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Derived one-row-per-date projection of Holiday, for equality joins against
 * transaction dates (e.g. from MCP). Do not hand-edit — rebuilt from `holidays`
 * by HolidayDayRebuildService.
 */
class HolidayDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'is_public',
        'is_school',
        'name',
    ];

    protected $casts = [
        'date'      => 'date:Y-m-d',
        'is_public' => 'boolean',
        'is_school' => 'boolean',
    ];
}
