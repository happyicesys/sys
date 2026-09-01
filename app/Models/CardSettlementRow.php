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
        'candidates_json' => 'json',
        'resolved_at' => 'datetime',
    ];

    public function report()
    {
        return $this->belongsTo(CardSettlementReport::class, 'card_settlement_report_id');
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

    public static function fingerprintFor(
        string $provider,
        string $terminalId,
        string $date,
        ?string $sequenceNo,
        int $amountCents,
        ?string $time
    ): string {
        return sha1(implode('|', [$provider, $terminalId, $date, $sequenceNo ?? '', $amountCents, $time ?? '']));
    }
}
