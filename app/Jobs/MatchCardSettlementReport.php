<?php

namespace App\Jobs;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Services\CardSettlement\CardSettlementMatcher;
use App\Services\CardSettlement\ParserRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Parses an uploaded card-settlement report (first run) and matches its rows
 * against vend_transactions. Re-dispatching on an already-ingested report
 * (the "Rematch" button — e.g. after adding a missing terminal binding) skips
 * ingestion and only re-runs the matcher over unresolved rows.
 */
class MatchCardSettlementReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 900;

    public function __construct(public int $reportId)
    {
        $this->onQueue('low');
    }

    public function handle(CardSettlementMatcher $matcher): void
    {
        $report = CardSettlementReport::find($this->reportId);
        if (! $report) {
            return;
        }

        try {
            $report->forceFill([
                'status' => CardSettlementReport::STATUS_MATCHING,
                'error_message' => null,
            ])->save();

            if (! $report->rows()->exists()) {
                $this->ingest($report);
            }

            $matcher->match($report);
        } catch (Throwable $e) {
            $report->forceFill([
                'status' => CardSettlementReport::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();

            throw $e;
        }
    }

    protected function ingest(CardSettlementReport $report): void
    {
        $attachment = $report->attachment;
        if (! $attachment || blank($attachment->local_url)) {
            throw new \RuntimeException('Report file attachment is missing.');
        }

        // The file lives on the report's private object-storage disk (DO
        // Spaces in prod); the parser wants a local path, so stage a temp copy.
        $tmpPath = tempnam(sys_get_temp_dir(), 'card-settlement-');
        file_put_contents($tmpPath, Storage::disk($report->fileDisk())->get($attachment->local_url));

        try {
            $parsed = ParserRegistry::for($report->provider)->parse($tmpPath);
        } finally {
            @unlink($tmpPath);
        }

        $report->forceFill([
            'merchant_account' => $parsed->merchantAccount,
            'cutover_date' => $parsed->cutoverDate,
            'report_generated_at' => $parsed->reportGeneratedAt,
            'total_rows' => count($parsed->rows),
            'purchase_rows' => collect($parsed->rows)->filter(fn ($r) => $r->isPurchase() && ! $r->isReversal)->count(),
            'reversal_rows' => collect($parsed->rows)->filter(fn ($r) => $r->isReversal)->count(),
        ])->save();

        $now = now();
        foreach (collect($parsed->rows)->chunk(500) as $chunk) {
            $fingerprints = $chunk->map(fn ($r) => CardSettlementRow::fingerprintFor(
                $report->provider, $r->terminalId, $r->transactionDate, $r->sequenceNo, $r->amountCents, $r->transactionTime
            ));

            // A fingerprint seen before means this line was already ingested —
            // either the same file re-uploaded or an overlapping cutover window
            // (the NETS business day spans two calendar dates). Keep the line
            // for the audit trail, marked DUPLICATE, never matched again.
            $existing = CardSettlementRow::query()
                ->whereIn('fingerprint', $fingerprints)
                ->pluck('fingerprint')
                ->flip();

            $insert = [];
            $seenInChunk = [];
            foreach ($chunk->values() as $i => $row) {
                $fingerprint = $fingerprints->values()[$i];
                $isDuplicate = $existing->has($fingerprint) || isset($seenInChunk[$fingerprint]);
                if ($isDuplicate) {
                    // Same fingerprint twice: keep a distinct one for this
                    // report's copy so the unique index doesn't reject it.
                    $fingerprint = sha1($fingerprint.'|report:'.$report->id.'|row:'.$row->rowNo);
                }
                $seenInChunk[$fingerprint] = true;

                $insert[] = [
                    'card_settlement_report_id' => $report->id,
                    'row_no' => $row->rowNo,
                    'txn_type' => $row->txnType,
                    'product' => $row->product,
                    'card_issuer' => $row->cardIssuer,
                    'terminal_id' => $row->terminalId,
                    'transaction_date' => $row->transactionDate,
                    'transaction_time' => $row->transactionTime,
                    'time_is_partial' => $row->timeIsPartial,
                    'amount_cents' => $row->amountCents,
                    'sequence_no' => $row->sequenceNo,
                    'is_reversal' => $row->isReversal,
                    'fingerprint' => $fingerprint,
                    'status' => $isDuplicate ? CardSettlementRow::STATUS_DUPLICATE : CardSettlementRow::STATUS_PENDING,
                    'resolution_note' => $isDuplicate ? 'Already ingested by an earlier report' : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            CardSettlementRow::insert($insert);
        }
    }
}
