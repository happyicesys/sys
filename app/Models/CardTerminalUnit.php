<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One physical card terminal: its acquirer TID plus the company that supplied
 * it. Managed from Data Management → Card Terminal.
 *
 * Do not confuse with {@see CardTerminal}, which is the COMPANY list
 * (Nayax / Nets / Nets-Auresys / PAX / MLS / HID) shown as "Card Terminal
 * Company". Which machine a unit sits on is NOT stored here — that is
 * {@see CardTerminalBinding}, created from the machine Setting/Edit page.
 */
class CardTerminalUnit extends Model
{
    use HasFactory;

    protected $table = 'card_terminal_units';

    /** Where is_will_auto_refund came from. The seed (partner workbook) and a manual edit are both authoritative; stats never flip the flag. */
    public const FLAG_SOURCE_SEED = 'seed';

    public const FLAG_SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'card_terminal_id',
        'terminal_id',
        'remarks',
        'batch',
        'is_will_auto_refund',
        'auto_refund_flag_source',
        'auto_refund_stats_json',
    ];

    protected $casts = [
        'auto_refund_stats_json' => 'json',
    ];

    /** The supplying company — a row in `card_terminals`. */
    public function company()
    {
        return $this->belongsTo(CardTerminal::class, 'card_terminal_id');
    }

    /**
     * Every binding ever recorded for this TID. Joined on the terminal_id
     * string rather than an FK because the settlement matcher resolves
     * bindings by (provider, terminal_id) and predates this table; constrain
     * with ->effectiveOn() at the call site to get the current one.
     */
    public function bindings()
    {
        return $this->hasMany(CardTerminalBinding::class, 'terminal_id', 'terminal_id');
    }

    /**
     * Does the NETS MerchantConnect file carry ALL of this terminal's sales?
     * Nets-Auresys terminals settle partly elsewhere (40–60 % coverage measured
     * 2026-09-08), so for them "no line" proves nothing — the reconciler
     * records `uncovered` instead of `not_captured` and never ticks a refund.
     * Keyed on the company name, never on the TID prefix (23077328 is a plain
     * Nets terminal with full coverage).
     */
    public function hasReportCoverageGap(): bool
    {
        $company = strtolower((string) $this->company?->name);

        return $company !== '' && in_array($company, (array) config('card_settlement.report_coverage_gap_companies', []), true);
    }

    /** true / false / null (unknown) — the tri-state the UI and the reconciler read. */
    public function willAutoRefund(): ?bool
    {
        return $this->is_will_auto_refund === null ? null : (bool) $this->is_will_auto_refund;
    }

    /**
     * The `card_terminal_bindings.provider` value a binding for this terminal
     * must carry, derived from its company — see config/card_settlement.php
     * for why Nets-Auresys resolves to 'nets' and why an unmapped company
     * resolves to something no report will match.
     */
    public function settlementProvider(): string
    {
        $company = $this->company?->name;

        if (! $company) {
            return config('card_settlement.default_provider', 'nets');
        }

        // Plain array lookup, NOT config() dot-notation: a company name
        // containing a dot would otherwise be read as a nested config path.
        $map = config('card_settlement.company_provider', []);
        if (isset($map[strtolower($company)])) {
            return $map[strtolower($company)];
        }

        // Unmapped company: a slug of its own name, which matches no settlement
        // report on purpose. Capped at 20 chars because that is the width of
        // card_terminal_bindings.provider — a long company name would otherwise
        // fail the INSERT and make the machine unsavable.
        return Str::substr(Str::slug($company), 0, 20)
            ?: config('card_settlement.default_provider', 'nets');
    }
}
