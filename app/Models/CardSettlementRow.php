<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardSettlementRow extends Model
{
    const STATUS_PENDING = 0;

    const STATUS_MATCHED = 1;

    const STATUS_UNMATCHED = 2;

    const STATUS_AMBIGUOUS = 3;   // >1 plausible sale — user picks

    const STATUS_IGNORED = 4;     // non-purchase (Logon…) or user-dismissed

    const STATUS_DUPLICATE = 5;   // same fingerprint already ingested by another report

    const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_MATCHED => 'Matched',
        self::STATUS_UNMATCHED => 'Unmatched',
        self::STATUS_AMBIGUOUS => 'Ambiguous',
        self::STATUS_IGNORED => 'Ignored',
        self::STATUS_DUPLICATE => 'Duplicate',
    ];

    protected $fillable = [
        'card_settlement_report_id',
        'row_no',
        'txn_type',
        'product',
        'card_issuer',
        'terminal_id',
        'transaction_date',
        'transaction_time',
        'time_is_partial',
        'amount_cents',
        'sequence_no',
        'is_reversal',
        'reverses_row_id',
        'reversed_by_row_id',
        'fingerprint',
        'status',
        'vend_id',
        'matched_vend_transaction_id',
        'match_time_delta',
        'candidates_json',
        'resolution_note',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'time_is_partial' => 'boolean',
        'is_reversal' => 'boolean',
        'candidates_json' => 'json',
        'resolved_at' => 'datetime',
    ];

    public function report()
    {
        return $this->belongsTo(CardSettlementReport::class, 'card_settlement_report_id');
    }

    /**
     * Purchase lines only — the lines that can ever represent money.
     *
     * NETS ships terminal housekeeping in the same file: ~320 zero-dollar
     * "Logon" lines a day, one per card terminal checking in with the host
     * (303 distinct terminals on the 2026-09-04 file). They can never match a
     * sale and ops can never act on them, so they are kept for file
     * reconciliation but never shown or counted (Brian, 2026-09-06).
     */
    public function scopeSaleLines($query)
    {
        return $query->where('txn_type', 'Purchase');
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendTransaction()
    {
        return $this->belongsTo(VendTransaction::class, 'matched_vend_transaction_id')
            ->withoutGlobalScopes();
    }

    /** On a reversal line: the purchase line it undoes. */
    public function reversedPurchase()
    {
        return $this->belongsTo(self::class, 'reverses_row_id');
    }

    /** On a purchase line: the reversal line that undid it. */
    public function reversal()
    {
        return $this->belongsTo(self::class, 'reversed_by_row_id');
    }

    /**
     * The identity of one settlement line, for cross-report dedupe.
     *
     * The time contributes only its MINUTE AND SECOND. An Excel re-save
     * destroys the hour ("23:12:41" → "12:41.0", stored as 00:12:41), so an
     * hour-sensitive key would see a round-tripped copy of an already-ingested
     * day as 2000 brand-new lines and match them all a second time. On
     * mm:ss the two spellings of one transaction collide, which is the point.
     *
     * Safe because terminal + date + sequence number + amount is already
     * near-unique: over 91,748 lines (35 daily files, Aug 1 – Sep 4 2026) and
     * again over all 94,943 rows in prod, this key produced ZERO collisions
     * between genuinely different transactions. Dropping the time entirely
     * does NOT hold — FlashPay stamps every line sequence 0, so two equal
     * FlashPay sales on one terminal in a day collide (126 such pairs).
     */
    public static function fingerprintFor(
        string $provider,
        string $terminalId,
        string $date,
        ?string $sequenceNo,
        int $amountCents,
        ?string $time
    ): string {
        return sha1(implode('|', [
            $provider,
            $terminalId,
            $date,
            $sequenceNo ?? '',
            $amountCents,
            self::minuteSecond($time),
        ]));
    }

    /** "23:12:41" → "12:41"; the hour is deliberately dropped (see above). */
    public static function minuteSecond(?string $time): string
    {
        if ($time === null || $time === '') {
            return '';
        }

        return preg_match('/^\d{1,2}:(\d{2}:\d{2})/', $time, $m) ? $m[1] : $time;
    }
}
