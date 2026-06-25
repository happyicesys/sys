<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row in a site's settlement ledger (Site Summary ▸ Payment History).
 *
 * See migration 2026_06_25_000000_create_customer_settlements_table for the
 * sign convention: amount_cents is +ve for a charge we owe the site owner and
 * -ve for a payment/waiver we make against it. The running balance is derived
 * (cumulative sum), never stored.
 */
class CustomerSettlement extends Model
{
    protected $table = 'customer_settlements';

    // Entry types.
    public const TYPE_OPENING_BALANCE = 'opening_balance';
    public const TYPE_LOCATION_FEE    = 'location_fee';
    public const TYPE_PAYMENT         = 'payment';
    public const TYPE_WAIVER          = 'waiver';
    public const TYPE_ADJUSTMENT      = 'adjustment';

    // Reference-number prefixes — SOA-style, one per entry type so the code
    // itself says what the line is (OB-/LF-/PMT-/WV-/ADJ-). Falls back to
    // REF_PREFIX_DEFAULT for any unmapped type.
    public const REF_PREFIX_DEFAULT         = 'SET';
    public const REF_PREFIX_OPENING_BALANCE = 'OB';
    public const REF_PREFIX_LOCATION_FEE    = 'LF';
    public const REF_PREFIX_PAYMENT         = 'PMT';
    public const REF_PREFIX_WAIVER          = 'WV';
    public const REF_PREFIX_ADJUSTMENT      = 'ADJ';

    /** entry_type => reference prefix. */
    public const REF_PREFIXES = [
        self::TYPE_OPENING_BALANCE => self::REF_PREFIX_OPENING_BALANCE,
        self::TYPE_LOCATION_FEE    => self::REF_PREFIX_LOCATION_FEE,
        self::TYPE_PAYMENT         => self::REF_PREFIX_PAYMENT,
        self::TYPE_WAIVER          => self::REF_PREFIX_WAIVER,
        self::TYPE_ADJUSTMENT      => self::REF_PREFIX_ADJUSTMENT,
    ];

    // Provenance.
    public const SOURCE_SEED        = 'seed';
    public const SOURCE_CRON        = 'cron';
    public const SOURCE_PAID_ACTION = 'paid-action';
    public const SOURCE_MANUAL      = 'manual';

    protected $fillable = [
        'reference_no',
        'customer_id',
        'operator_id',
        'entry_date',
        'year_month',
        'entry_type',
        'amount_cents',
        'item',
        'remarks',
        'customer_period_summary_id',
        'source',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'entry_date'   => 'date',
        'year_month'   => 'date',
        'amount_cents' => 'integer',
    ];

    /**
     * The SOA-style reference prefix for an entry type (e.g. LF for a location
     * fee). Unmapped types fall back to REF_PREFIX_DEFAULT.
     */
    public static function refPrefixFor(?string $entryType): string
    {
        return self::REF_PREFIXES[$entryType] ?? self::REF_PREFIX_DEFAULT;
    }

    /**
     * Auto-assign a unique, human-friendly reference (e.g. LF-000123) to every
     * new row, derived from its entry type + id. Fires for ALL creation paths —
     * seeder, monthly cron, and the Paid-action credit — so the logic lives in
     * exactly one place and never needs duplicating.
     */
    protected static function booted(): void
    {
        static::created(function (self $row) {
            if (!empty($row->reference_no)) {
                return;
            }
            $ref = self::refPrefixFor($row->entry_type)
                . '-' . str_pad((string) $row->getKey(), 6, '0', STR_PAD_LEFT);
            // Bounded update without re-firing model events / touching audit.
            static::withoutEvents(function () use ($row, $ref) {
                $row->newQuery()->whereKey($row->getKey())->update(['reference_no' => $ref]);
            });
            $row->reference_no = $ref;
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function periodSummary()
    {
        return $this->belongsTo(CustomerPeriodSummary::class, 'customer_period_summary_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Chronological order used for ledger display + running-balance maths. */
    public function scopeChronological($query)
    {
        return $query->orderBy('entry_date')->orderBy('id');
    }
}
