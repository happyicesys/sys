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
        'cutover_date',
        'report_generated_at',
        'status',
        'total_rows',
        'purchase_rows',
        'matched_count',
        'unmatched_count',
        'ambiguous_count',
        'duplicate_count',
        'ignored_count',
        'synced_count',
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

    /** Recount row statuses into the summary columns. */
    public function refreshCounts(): void
    {
        $byStatus = $this->rows()
            ->selectRaw('status, COUNT(*) AS n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $this->forceFill([
            'matched_count' => $byStatus->get(CardSettlementRow::STATUS_MATCHED, 0),
            'unmatched_count' => $byStatus->get(CardSettlementRow::STATUS_UNMATCHED, 0),
            'ambiguous_count' => $byStatus->get(CardSettlementRow::STATUS_AMBIGUOUS, 0),
            'duplicate_count' => $byStatus->get(CardSettlementRow::STATUS_DUPLICATE, 0),
            'ignored_count' => $byStatus->get(CardSettlementRow::STATUS_IGNORED, 0),
        ])->save();
    }
}
