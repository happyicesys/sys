<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\CustomerPeriodSummary;
use App\Models\CustomerPeriodSummaryInvoice;
use App\Services\CustomerInvoiceService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * POSTs an API Report invoice to the CMS /api/transactions/deals endpoint
 * (same one OpsJob's SyncOpsJobTransactionCMS uses) and persists the
 * returned transaction_id back onto a customer_period_summary_invoices
 * row.
 *
 * The row is created BEFORE the job is queued (in the controller) so the
 * UI has an immediate "in flight" record. The job updates that row when
 * the response comes back. This mirrors how OpsJob ties cms_transaction_id
 * back to ops_job_items (just with a dedicated tracking table since the
 * Customer Summary flow has no equivalent of ops_job_items).
 */
class SyncCustomerInvoiceCMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $retryAfter = 5;

    public function __construct(
        protected int $invoiceId,
        protected int $customerId,
        protected ?int $summaryId,
        protected string $periodStart,
        protected string $periodEnd,
        protected ?int $userId,
    ) {}

    public function handle(CustomerInvoiceService $service): void
    {
        $baseUrl = config('app.cms_url');
        if (!$baseUrl) {
            Log::warning('SyncCustomerInvoiceCMS skipped: cms_url not configured');
            return;
        }

        $invoice = CustomerPeriodSummaryInvoice::find($this->invoiceId);
        if (!$invoice) {
            Log::warning('SyncCustomerInvoiceCMS aborted: invoice row missing', ['invoice_id' => $this->invoiceId]);
            return;
        }

        // Eager-load operator so CustomerInvoiceService can read
        // operator->gst_vat_rate for PS-family GST de-grossing (avoids
        // a lazy-load query inside the queue worker).
        $customer = Customer::with('operator:id,gst_vat_rate')->find($this->customerId);
        if (!$customer) {
            Log::warning('SyncCustomerInvoiceCMS aborted: customer missing', ['customer_id' => $this->customerId]);
            return;
        }

        $summary = $this->summaryId ? CustomerPeriodSummary::find($this->summaryId) : null;

        $payload = $service->buildPayload(
            $customer,
            Carbon::parse($this->periodStart),
            Carbon::parse($this->periodEnd),
            $summary,
            [
                'reference_id' => $invoice->id,
                // The CMS deal endpoint validates that "driver" / "created_by"
                // are populated; we use the queuing user's name when present
                // (auth() is null in queue workers).
                'driver' => optional(\App\Models\User::find($this->userId))->username ?? 'system',
                'created_by' => optional(\App\Models\User::find($this->userId))->username ?? 'system',
            ]
        );

        if (!$payload) {
            // Contract type not invoiceable — should have been caught at
            // the controller, but guard here too so a stray call doesn't
            // leave the row in a misleading "pending" state.
            Log::warning('SyncCustomerInvoiceCMS skipped: payload empty (non-invoiceable contract)', [
                'invoice_id' => $invoice->id,
                'customer_id' => $customer->id,
            ]);
            $invoice->update([
                'response' => ['error' => 'non_invoiceable_contract_type'],
            ]);
            return;
        }

        $invoice->update([
            'payload' => $payload,
            'total_amount_cents' => $service->totalCentsFromPayload($payload),
        ]);

        $endpoint = rtrim($baseUrl, '/') . '/api/transactions/deals';

        Log::info('SyncCustomerInvoiceCMS Request:', [
            'endpoint' => $endpoint,
            'invoice_id' => $invoice->id,
            'data' => $payload,
        ]);

        // SSL verification: ON in production (real cert), OFF anywhere
        // else (local Herd / Valet uses self-signed certs for *.test
        // domains, which curl can't validate without a custom cacert).
        // Same env gate Laravel itself uses for debug-mode-style toggles
        // — never weakens prod security.
        $verify = app()->environment('production');

        $response = Http::withOptions(['verify' => $verify])->post($endpoint, $payload);

        Log::info('SyncCustomerInvoiceCMS Response:', [
            'invoice_id' => $invoice->id,
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        $body = $response->json();
        $invoice->response = $body;

        if ($response->successful()) {
            // Resolve transaction_id from the same shape OpsJob expects:
            // [{ ops_job_item_id, transaction_id }, ...]. The CMS keys on
            // person_id when ops_job_item_id is null, so accept the first
            // returned record.
            $transactionId = null;
            if (is_array($body)) {
                foreach ($body as $record) {
                    if (is_array($record) && isset($record['transaction_id'])) {
                        $transactionId = $record['transaction_id'];
                        break;
                    }
                }
            }

            if ($transactionId) {
                $invoice->cms_transaction_id = $transactionId;
                $invoice->cms_transaction_at = Carbon::now();
                $invoice->cms_transaction_by = $this->userId;
            }
        }

        $invoice->save();
    }
}
