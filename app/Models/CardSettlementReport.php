<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardSettlementReport extends Model
{
    // Lifecycle: uploaded → matching → review (user resolves queries) → synced.
    const STATUS_UPLOADED = 'uploaded';

    const STATUS_MATCHING = 'matching';

    const STATUS_REVIEW = 'review';

    const STATUS_SYNCED = 'synced';

    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'provider',
        'merchant_account',
        'original_filename',
        'storage_disk',
        'cutover_date',
        'report_generated_at',
        'status',
        'total_rows',
        'purchase_rows',
        'reversal_rows',
        'partial_time_rows',
        'matched_count',
        'unmatched_count',
        'ambiguous_count',
        'duplicate_count',
        'ignored_count',
        'synced_count',
        'refunded_count',
        'error_message',
        'uploaded_by',
        'matched_at',
        'synced_at',
        'synced_by',
    ];

    protected $casts = [
        'cutover_date' => 'date',
        'report_generated_at' => 'datetime',
        'matched_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Reversal pairs cross reports (a reversal after the cutover sits in the
        // next day's file). The DB cascade only removes THIS report's rows, so
        // release the links held by rows in other reports first: a purchase
        // line left pointing at a vanished reversal would (a) be skipped by the
        // matcher on re-upload of the deleted file and (b) still be marked
        // refunded on Sync with no reversal behind it; a reversal line pointing
        // at a vanished purchase goes back to a query so Rematch re-pairs it.
        static::deleting(function (self $report) {
            $ids = $report->rows()->pluck('id');
            if ($ids->isEmpty()) {
                return;
            }

            CardSettlementRow::whereIn('reversed_by_row_id', $ids)
                ->where('card_settlement_report_id', '!=', $report->id)
                ->update(['reversed_by_row_id' => null]);

            CardSettlementRow::whereIn('reverses_row_id', $ids)
                ->where('card_settlement_report_id', '!=', $report->id)
                ->update([
                    'reverses_row_id' => null,
                    'status' => CardSettlementRow::STATUS_UNMATCHED,
                    'match_time_delta' => null,
                    'resolution_note' => 'Original purchase report deleted — Rematch to pair again',
                ]);
        });
    }

    /**
     * Disk for uploaded report files: private DO Spaces (S3) by config, with
     * the same credentials guard config/filesystems.php applies to the default
     * disk so a dev/test box without Spaces keys quietly uses 'local'.
     */
    public static function storageDisk(): string
    {
        $disk = config('card_settlement.storage_disk', 'digitaloceanspaces');

        if ($disk === 'digitaloceanspaces'
            && (! config('filesystems.disks.digitaloceanspaces.key') || ! config('filesystems.disks.digitaloceanspaces.secret'))) {
            return 'local';
        }

        return $disk;
    }

    /** The disk this report's file was written to (falls back to the configured one). */
    public function fileDisk(): string
    {
        return $this->storage_disk ?: self::storageDisk();
    }

    public function rows()
    {
        return $this->hasMany(CardSettlementRow::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable');
    }

    public function attachment()
    {
        return $this->morphOne(Attachment::class, 'modelable')->latestOfMany();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function syncer()
    {
        return $this->belongsTo(User::class, 'synced_by');
    }

    /**
     * Recount row statuses into the summary columns. matched_count is
     * purchases only — a paired reversal line is MATCHED too, but it claims no
     * sale of its own and must not inflate "Sync N matched".
     */
    public function refreshCounts(): void
    {
        $byStatus = $this->rows()
            ->selectRaw('status, COUNT(*) AS n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $matchedPurchases = $this->rows()
            ->where('status', CardSettlementRow::STATUS_MATCHED)
            ->where('is_reversal', false)
            ->count();

        $this->forceFill([
            'matched_count' => $matchedPurchases,
            'unmatched_count' => $byStatus->get(CardSettlementRow::STATUS_UNMATCHED, 0),
            'ambiguous_count' => $byStatus->get(CardSettlementRow::STATUS_AMBIGUOUS, 0),
            'duplicate_count' => $byStatus->get(CardSettlementRow::STATUS_DUPLICATE, 0),
            'ignored_count' => $byStatus->get(CardSettlementRow::STATUS_IGNORED, 0),
        ])->save();
    }
}
