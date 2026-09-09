<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Telco extends Model
{
    use HasFactory;

    /**
     * The colours a package may be tinted with on the Operation Dashboard's
     * "SimCard Package" badge (Brian, 2026-09-09).
     *
     * Green, grey and pink/red are deliberately absent: that page already
     * spends them on online / N-A / offline status, so re-using one for a
     * package would read as a machine state. NULL colour = the default blue
     * tint the badge has always carried.
     *
     * Keys only — the actual swatches live in resources/js/constants/telcoColors.js,
     * as inline styles, because Tailwind purges class names it cannot see.
     */
    public const COLORS = ['yellow', 'orange', 'white', 'purple', 'blue'];

    protected $fillable = [
        'name',
        'desc',
        'remarks',
        // Retired packages are deactivated, never deleted — see the
        // 2026_09_09 migration. Only settable while no SIM on the package is
        // bound to a machine (TelcoController::toggleActivateDeactivate).
        'is_active',
        // One of self::COLORS, or null for the default badge tint.
        'color',
        // config/simcard_usage.php provider key ('voiceping', ...) — null means
        // this telco has no usage API and simcards:sync-usage skips its sims.
        'usage_provider',
        // Optional per-package override of that provider's default endpoint —
        // the "API query link" on the SimCard Package form. Null = provider default.
        'usage_endpoint',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // relationships
    public function simcards()
    {
        return $this->hasMany(Simcard::class);
    }
}
