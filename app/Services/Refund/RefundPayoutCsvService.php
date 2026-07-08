<?php

namespace App\Services\Refund;

use App\Models\RefundPayoutBatch;
use App\Models\RefundTicket;
use App\Services\Refund\BankTemplates\BankTemplateRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Builds a bulk PayNow transfer CSV from approved tickets and links them into a
 * batch (replaces keying refunds into the bank one-by-one).
 */
class RefundPayoutCsvService
{
    protected RefundTicketService $tickets;

    public function __construct(RefundTicketService $tickets)
    {
        $this->tickets = $tickets;
    }

    public function generate(array $ticketIds, ?int $userId = null): RefundPayoutBatch
    {
        return DB::transaction(function () use ($ticketIds, $userId) {
            // Only approved PayNow tickets that are not already in a batch.
            $tickets = RefundTicket::whereIn('id', $ticketIds)
                ->where('status', RefundTicket::STATUS_APPROVED)
                ->where('refund_method', RefundTicket::METHOD_PAYNOW)
                ->whereNull('payout_batch_id')
                ->get();

            $batch = RefundPayoutBatch::create([
                'reference' => 'PENDING',
                'method' => RefundTicket::METHOD_PAYNOW,
                'created_by' => $userId,
                'count' => 0,
                'total_cents' => 0,
                'status' => RefundPayoutBatch::STATUS_GENERATED,
            ]);

            $prefix = config('refund.batch_reference_prefix', 'BATCH');
            $batch->reference = $prefix . '-' . str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT);

            $columns = (array) config('refund.paynow_csv_columns', ['reference', 'payout_destination', 'amount', 'contact_email']);
            $rows = [implode(',', $columns)];
            $total = 0;

            foreach ($tickets as $ticket) {
                $map = [
                    'reference' => $ticket->reference,
                    'payout_destination' => $ticket->payout_destination,
                    'amount' => number_format($ticket->payout_amount_cents / 100, 2, '.', ''),
                    'contact_email' => $ticket->contact_email,
                ];
                $rows[] = implode(',', array_map(fn ($c) => $this->csvEscape($map[$c] ?? ''), $columns));
                $total += (int) $ticket->payout_amount_cents;

                $ticket->update([
                    'payout_batch_id' => $batch->id,
                    'status' => RefundTicket::STATUS_SCHEDULED,
                    'scheduled_at' => now(),
                ]);
                $this->tickets->log($ticket, 'scheduled', RefundTicket::STATUS_APPROVED, RefundTicket::STATUS_SCHEDULED, 'Added to PayNow batch ' . $batch->reference, 'System', $userId);
            }

            $path = 'refund-payouts/' . $batch->reference . '.csv';
            Storage::disk('local')->put($path, implode("\n", $rows));

            $batch->update([
                'csv_path' => $path,
                'count' => $tickets->count(),
                'total_cents' => $total,
            ]);

            return $batch->fresh();
        });
    }

    /**
     * Generate a bank bulk-transfer file from selected approved PayNow tickets,
     * link them into a batch, and return the file content for download.
     *
     * @return array{filename: string, content: string, batch: RefundPayoutBatch}
     */
    public function exportBank(array $ticketIds, string $bankKey, ?int $userId = null): array
    {
        $template = BankTemplateRegistry::make($bankKey);

        return DB::transaction(function () use ($ticketIds, $bankKey, $userId, $template) {
            // Approved PayNow tickets — exporting does NOT change status, so the admin
            // can re-export the batch CSV any number of times.
            $tickets = RefundTicket::whereIn('id', $ticketIds)
                ->where('status', RefundTicket::STATUS_APPROVED)
                ->where('refund_method', RefundTicket::METHOD_PAYNOW)
                ->get();

            if ($tickets->isEmpty()) {
                throw new \RuntimeException('No eligible approved PayNow tickets selected.');
            }

            // The bank file header carries a single originating account, so a
            // batch must not mix operators — export one operator at a time.
            $operatorIds = $tickets->pluck('operator_id')->unique()->values();
            if ($operatorIds->count() > 1) {
                throw new \RuntimeException('Selected tickets belong to different operators. The bank file header holds one originating account — please export each operator separately.');
            }
            $operator = $operatorIds->first()
                ? \App\Models\Operator::withoutGlobalScopes()->find($operatorIds->first())
                : null;

            $batch = RefundPayoutBatch::create([
                'reference' => 'PENDING',
                'method' => RefundTicket::METHOD_PAYNOW,
                'created_by' => $userId,
                'count' => 0,
                'total_cents' => 0,
                'status' => RefundPayoutBatch::STATUS_GENERATED,
            ]);
            $batch->reference = config('refund.batch_reference_prefix', 'BATCH') . '-' . str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT);

            $content = $template->generate($tickets, ['batch' => $batch, 'operator' => $operator]);
            $filename = $batch->reference . '-' . $bankKey . '.' . $template->fileExtension();
            $path = 'refund-payouts/' . $filename;
            Storage::disk('local')->put($path, $content);

            $total = 0;
            foreach ($tickets as $ticket) {
                $total += (int) $ticket->payout_amount_cents;
                // record the latest export batch for reference; keep status = Approved
                $ticket->update(['payout_batch_id' => $batch->id]);
                $this->tickets->log($ticket, 'exported', null, null, 'Exported in ' . strtoupper($bankKey) . ' batch ' . $batch->reference, 'System', $userId);
            }

            $batch->update(['csv_path' => $path, 'count' => $tickets->count(), 'total_cents' => $total]);

            return ['filename' => $filename, 'content' => $content, 'batch' => $batch->fresh()];
        });
    }

    protected function csvEscape($value): string
    {
        $value = (string) $value;
        if (preg_match('/[",\n]/', $value)) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
