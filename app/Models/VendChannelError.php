<?php

namespace App\Models;

use App\Support\DispenseVerdict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class VendChannelError extends Model
{
    use HasFactory;

    /** Server-defined "Machine transaction not found (NA)". See DispenseVerdict. */
    public const CODE_NOT_FOUND = DispenseVerdict::NOT_FOUND_CODE;

    protected $fillable = [
        'code',
        'desc',
        'weightage',
    ];

    // protected $dates = [
    //     'created_at'
    // ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Sub-select of the ids whose code is in $codes — for the Eloquent scopes on
     * vend_transactions that filter by FK rather than by joined code. The table
     * holds a dozen rows, so the semi-join is free.
     */
    public static function idsForCodes(array $codes): Builder
    {
        return static::query()->select('id')->whereIn('code', $codes);
    }

    /**
     * Resolve the error row for a code the MACHINE sent on a TRADE frame.
     *
     * Server-reserved codes (99 = "not found") are refused here: a frame that
     * claims one gets NULL (no verdict) and a warning, so a rogue or spoofed
     * frame can never dress itself up as "TRADE never arrived" and slip past
     * the refund and sales rules. Only the marking jobs may write 99.
     *
     * @param  Collection<int|string, VendChannelError>|null  $byCode  a keyBy('code') cache when the caller has one
     */
    public static function forFrameCode(int|string|null $code, ?Collection $byCode = null, ?string $vendCode = null): ?self
    {
        $normalised = DispenseVerdict::code($code);
        if ($normalised === null) {
            return null;
        }

        if (DispenseVerdict::isServerReserved($normalised)) {
            Log::warning('TRADE frame carried a server-reserved channel error code; stored as no verdict.', [
                'code' => $code,
                'vend_code' => $vendCode,
            ]);

            return null;
        }

        if ($byCode !== null) {
            return $byCode->get($normalised) ?? $byCode->get((string) $normalised);
        }

        return static::query()->where('code', $normalised)->first();
    }
}
