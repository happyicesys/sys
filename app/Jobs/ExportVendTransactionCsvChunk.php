<?php

namespace App\Jobs;

use App\Jobs\Concerns\AppendsUnreportedGatewayCsvRows;
use App\Models\Operator;
use App\Models\RefundTicketItem;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Models\ExportJob;
use App\Models\ExportJobChunk;
use App\Models\Tag;
use App\Models\User;
use DB;
use App\Jobs\ZipVendTransactionCsvExport;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportVendTransactionCsvChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, AppendsUnreportedGatewayCsvRows;

    public $tries = 1;
    public $timeout = 1800; // 30-minute hard cap

    protected $chunkIndex;
    protected $chunkSize;
    protected $jobId;
    protected $requestData;
    protected $userID;
    protected $minId; // keyset lower bound (inclusive)
    protected $maxId; // keyset upper bound (inclusive)

    public function __construct($jobId, array $requestData, $userID = null, $chunkIndex, $chunkSize, $minId = null, $maxId = null)
    {
        $this->chunkIndex = $chunkIndex;
        $this->chunkSize = $chunkSize;
        $this->jobId = $jobId;
        $this->requestData = $requestData;
        $this->userID = $userID;
        $this->minId = $minId;
        $this->maxId = $maxId;
    }

    public function handle()
    {
        $job = ExportJob::find($this->jobId);
        if (!$job)
            return;

        $chunk = ExportJobChunk::where('export_job_id', $this->jobId)
            ->where('chunk_index', $this->chunkIndex)
            ->first();
        if (!$chunk)
            return;


        $user = User::find($this->userID ?? $job->user_id);

        try {
            $request = new Request($this->requestData);
            $request->merge([
                'date_from' => $request->date_from ? Carbon::parse($request->date_from)->setTimezone(env('APP_TIMEZONE'))->startOfDay() : Carbon::today()->setTimezone(env('APP_TIMEZONE'))->startOfDay(),
                'date_to' => $request->date_to ? Carbon::parse($request->date_to)->setTimezone(env('APP_TIMEZONE'))->endOfDay() : Carbon::today()->setTimezone(env('APP_TIMEZONE'))->endOfDay(),
                'sortKey' => $request->sortKey ?? 'transaction_datetime',
                'sortBy' => $request->sortBy ?? false
            ]);

            if (!$request->operators) {
                if ($user->operator->code == 'HIPL') {
                    $request->merge([
                        'operators' => [
                            $user->operator_id,
                            Operator::where('code', 'HIMD')->first()?->id,
                            Operator::where('code', 'LEA')->first()?->id,
                            Operator::where('code', 'HIESG')->first()?->id,
                            Operator::where('code', 'UL-ST')->first()?->id,
                        ]
                    ]);
                } else {
                    $request->merge(['operators' => [$user->operator_id]]);
                }
            }

            $filename = 'vend_transactions_' . now()->format('Ymd_His') . "_part{$this->chunkIndex}.csv";

            $spacesPath = "sys/exports/{$filename}";

            // Align with the transaction page aggregate cards: exclude testing
            // machines and non-settled rows (see filters on the query below) so
            // the exported Amount total tallies with the dashboard "Total Sales".
            $testingVendIds = Cache::remember('testing_vend_ids', 3600, fn() =>
                DB::table('vends')->where('is_testing', true)->pluck('id')->map(fn($v) => (int) $v)->all()
            );

            $stream = fopen('php://temp', 'r+');

            fputcsv($stream, [
                'Order ID',
                'Transaction Datetime',
                'Machine ID',
                'Machine Prefix',
                'Customer ID',
                'Customer Code',
                'Customer Name',
                'Channel',
                'Product Code',
                'Product Name',
                'Price Type',
                'Amount',
                'Amount Breakdown',
                'Unit Cost',
                'Payment Method',
                'Cashless Mfg',
                'Error Code',
                'Location Type',
                'Operator',
                'Payment Status',
                'Is Refunded',
                'Is Multiple',
                'Multiple Qty',
                'TXN Source',
                'Member ID',
                'HID Card ID',
                'Voucher',
                'Campaign Labels',
                'Dispense Attempted?',
                'Refund Request',
                'Refund Status',
            ]);

            VendTransaction::query()
                ->leftJoin('customers', 'customers.id', '=', 'vend_transactions.customer_id')
                ->leftJoin('location_types', 'location_types.id', '=', 'customers.location_type_id')
                ->join('vends', 'vends.id', '=', 'vend_transactions.vend_id')
                ->leftJoin('operators', 'operators.id', '=', 'vend_transactions.operator_id')
                ->leftJoin('payment_methods', 'payment_methods.id', '=', 'vend_transactions.payment_method_id')
                ->leftJoin('products', 'products.id', '=', 'vend_transactions.product_id')
                ->leftJoin('unit_costs', 'unit_costs.id', '=', 'vend_transactions.unit_cost_id')
                ->leftJoin('vend_channels', 'vend_channels.id', '=', 'vend_transactions.vend_channel_id')
                ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_transactions.vend_channel_error_id')
                ->leftJoin('vend_prefixes', 'vend_prefixes.id', '=', 'vends.vend_prefix_id')
                // 1:1 gateway log (belongsTo via payment_gateway_log_id) so the CSV
                // can surface "Dispense Attempted?" the same way the Payment Gateway
                // Transactions page does (payment_gateway_logs.is_dispensed).
                ->leftJoin('payment_gateway_logs', 'payment_gateway_logs.id', '=', 'vend_transactions.payment_gateway_log_id')
                ->when($user->vends()->exists(), function ($query) use ($user) {
                    $query->whereIn('vend_transactions.vend_id', $user->vends->pluck('id'));
                })
                ->filterTransactionIndex($request)
                // Mirror the aggregate-cards query (VendController@transactionIndex):
                // only settled sales (excludes in-flight PENDING and voided REFUNDED
                // gateway rows) and no testing machines. Without these the CSV
                // contains rows the "Total Sales" card never counts, so the
                // exported total comes out higher than the dashboard.
                // NOTE: kept in sync with the id-boundary query in
                // VendController@exportTransactionCsv.
                ->where('vend_transactions.settlement_status', VendTransaction::SETTLEMENT_SETTLED)
                ->when(!empty($testingVendIds), fn($q) => $q->whereNotIn('vend_transactions.vend_id', $testingVendIds))
                ->select([
                    'vend_transactions.*',
                    'vends.code AS vend_code',
                    'vends.name AS vend_name',
                    'vend_prefixes.name AS vend_prefix_name',
                    'customers.id AS customer_id',
                    'customers.code AS customer_code',
                    'customers.name AS customer_name',
                    'customers.person_id',
                    'customers.virtual_customer_code',
                    'location_types.name AS location_type_name',
                    'operators.code AS operator_code',
                    'products.code AS product_code',
                    'products.name AS product_name',
                    'payment_methods.name AS payment_method_name',
                    'unit_costs.cost',
                    'vend_channels.amount AS vend_channel_amount',
                    'vend_channels.amount2 AS vend_channel_amount2',
                    'vend_channel_errors.code AS vend_channel_error_code',
                    'payment_gateway_logs.is_dispensed AS pg_is_dispensed',
                    DB::raw('vend_transactions.label_json AS label_ids_json'),
                ])
                ->orderBy('vend_transactions.id')
                // Keyset pagination: use ID boundaries set by the controller so we
                // never rely on OFFSET (which MySQL must scan-and-discard, and which
                // Laravel's chunk() internally overrides anyway via forPage()).
                ->when($this->minId !== null && $this->maxId !== null, function ($q) {
                    $q->whereBetween('vend_transactions.id', [$this->minId, $this->maxId]);
                }, function ($q) {
                    // Legacy fallback for any jobs already in-queue without ID bounds
                    $q->skip($this->chunkIndex * $this->chunkSize)
                      ->take($this->chunkSize);
                })
                ->chunk(500, function ($transactions) use ($stream) {
                    $transactionIds = $transactions->pluck('id');

                    // Pull items for this chunk (unchanged)
                    $items = VendTransactionItem::with([
                        'vendChannel:id,code,amount',
                        'product:id,code,name',
                        'unitCost:id,cost',
                        'vendChannelError:id,code,desc',
                    ])
                        ->whereIn('vend_transaction_id', $transactionIds)
                        ->get()
                        ->groupBy('vend_transaction_id');

                    // Per-item refund-request placement (mirrors the on-screen
                    // Transactions table): a multiple-purchase refund that targets a
                    // specific SKU shows on that item row, not the parent. Keyed off
                    // the denormalised vend_transactions.refund_request_id already
                    // selected on each row; one bounded, index-backed lookup per chunk
                    // (refund_ticket_items.refund_ticket_id is indexed).
                    $refundTicketIds = $transactions
                        ->filter(fn($t) => $t->is_multiple && filled($t->refund_request_id))
                        ->pluck('refund_request_id')
                        ->unique()
                        ->values()
                        ->all();

                    $refundTicketItems = empty($refundTicketIds)
                        ? collect()
                        : RefundTicketItem::query()
                            ->whereIn('refund_ticket_id', $refundTicketIds)
                            ->get(['refund_ticket_id', 'vend_transaction_item_id', 'vend_channel_code'])
                            ->groupBy('refund_ticket_id');

                    // 🔹 Collect all label values (ints and strings) across this chunk
                    $rawLabelVals = $transactions->pluck('label_ids_json')
                        ->filter()
                        ->flatMap(function ($val) {
                        if (is_array($val))
                            return $val;
                        $arr = json_decode($val, true);
                        return is_array($arr) ? $arr : [];
                    });

                    $tagIds = $rawLabelVals
                        ->filter(fn($v) => is_int($v) || (is_string($v) && ctype_digit($v)))
                        ->map(fn($v) => (int) $v)
                        ->unique()
                        ->values();

                    $tagNames = $rawLabelVals
                        ->filter(fn($v) => is_string($v) && !ctype_digit($v))
                        ->unique()
                        ->values();

                    // 🔹 Fetch tags by id
                    $tagsById = Tag::whereIn('id', $tagIds)
                        ->get(['id', 'name', 'slug'])
                        ->keyBy('id');

                    // 🔹 Fetch tags by name or slug and index by both values
                    $tagsByNameSlug = $tagNames->isEmpty()
                        ? []
                        : Tag::whereIn('name', $tagNames)
                            ->orWhereIn('slug', $tagNames)
                            ->get(['id', 'name', 'slug'])
                            ->reduce(function ($carry, $tag) {
                            $carry[$tag->name] = $tag;
                            $carry[$tag->slug] = $tag;
                            return $carry;
                        }, []);

                    foreach ($transactions as $txn) {
                        // Normalize label values for this txn (could be ints or strings)
                        $vals = is_array($txn->label_ids_json)
                            ? $txn->label_ids_json
                            : (json_decode($txn->label_ids_json, true) ?: []);

                        // Build Labels string honoring provided order
                        $labelStr = collect($vals)
                            ->map(function ($v) use ($tagsById, $tagsByNameSlug) {
                            if (is_int($v) || (is_string($v) && ctype_digit($v))) {
                                $t = $tagsById->get((int) $v);
                            } else {
                                $t = $tagsByNameSlug[$v] ?? null;
                            }
                            return $t->name ?? $t->slug ?? (string) $v;
                        })
                            ->implode(', ');

                        // existing JSON parsing
                        $txn_json = is_array($txn->vend_transaction_json)
                            ? $txn->vend_transaction_json
                            : json_decode($txn->vend_transaction_json, true);

                        $meta_json = is_array($txn->meta_json)
                            ? $txn->meta_json
                            : json_decode($txn->meta_json, true);

                        $txnItems = $items[$txn->id] ?? collect();

                        $main_amount = $txn->amount / 100;
                        $multipleBreakdown = $txn->is_multiple
                            ? ($txn->amount - $txnItems->sum(fn($item) => $item->vendChannel?->amount ?? 0)) / 100
                            : $main_amount;

                        // Wrap order_id in Excel text-formula so long numeric IDs
                        // do not get converted to scientific notation when the
                        // CSV is opened directly in Excel.
                        $orderIdCell = $txn->order_id !== null && $txn->order_id !== ''
                            ? '="' . $txn->order_id . '"'
                            : '';

                        // "Dispense Attempted?" mirrors the Payment Gateway
                        // Transactions page: Yes/No when the sale is backed by a
                        // gateway log, blank for non-gateway (cash/card) sales.
                        $dispenseAttempted = $txn->payment_gateway_log_id
                            ? ((int) $txn->pg_is_dispensed === 1 ? 'Yes' : 'No')
                            : '';

                        // Decide whether the refund badge belongs on the item rows
                        // (targeted SKU inside a multiple purchase) or the parent row.
                        $refundTargetItemIds = [];
                        $refundTargetChannelCodes = [];
                        $refundOnItems = false;
                        if ($txn->is_multiple && filled($txn->refund_request_id)) {
                            $ticketItems = $refundTicketItems->get($txn->refund_request_id);
                            if ($ticketItems && $ticketItems->isNotEmpty()) {
                                $refundTargetItemIds = $ticketItems->pluck('vend_transaction_item_id')
                                    ->filter()->map(fn($v) => (int) $v)->unique()->all();
                                $refundTargetChannelCodes = $ticketItems->pluck('vend_channel_code')
                                    ->filter()->map(fn($v) => (string) $v)->unique()->all();
                                foreach ($txnItems as $it) {
                                    if (in_array((int) $it->id, $refundTargetItemIds, true)
                                        || (filled($it->vend_channel_code)
                                            && in_array((string) $it->vend_channel_code, $refundTargetChannelCodes, true))) {
                                        $refundOnItems = true;
                                        break;
                                    }
                                }
                            }
                        }
                        // Parent row shows the refund reference only when it is NOT
                        // pinned to a specific item row.
                        $headerRefundRef = ($txn->refund_request_reference && !$refundOnItems)
                            ? $txn->refund_request_reference : '';
                        $headerRefundStatus = ($txn->refund_request_reference && !$refundOnItems)
                            ? ($txn->refund_request_status ?? '') : '';

                        // ✏️ Parent row — append $labelStr at the end
                        fputcsv($stream, [
                            $orderIdCell,
                            \Carbon\Carbon::parse($txn->transaction_datetime)->toDateTimeString(),
                            $txn->vend_code ?? '',
                            $txn->vend_prefix_name ?? '',
                            $txn->customer_id + 20000,
                            $txn->person_id ? $txn->virtual_customer_code : '',
                            $txn->customer_name,
                            $txn->vend_channel_code ?? '',
                            $txn->product_code,
                            $txn->vend_channel_code == 0 && !$txn->product_code ? 'Multiple Purchase' : $txn->product_name,
                            $txn->vend_channel_amount == $txn->amount ? 'P1' : ($txn->vend_channel_amount2 == $txn->amount ? 'P2' : ''),
                            $main_amount,
                            $multipleBreakdown,
                            $txn->cost ? $txn->cost / 100 : '',
                            $txn->payment_method_name,
                            $txn->cashless_mfg ?? '',
                            $txn->vend_channel_error_code,
                            $txn->location_type_name,
                            $txn->operator_code,
                            in_array($txn->vend_channel_error_code, [null, 0, 6]) ? 'Successful' : 'Unsuccessful',
                            $txn->is_refunded ? 'Yes' : '',
                            $txn->is_multiple ? 'Yes' : 'No',
                            $txn->is_multiple ? $txnItems->count() : 1,
                            $txn->interface_type,
                            $txn_json['dcvend_user_id'] ?? '',
                            $meta_json['hid_card_id'] ?? '',
                            (!empty($meta_json['vouchers']) ? ($meta_json['vouchers'][0]['code'] ?? '') : ''),
                            $labelStr, // 👈 new
                            $dispenseAttempted,
                            $headerRefundRef,
                            $headerRefundStatus,
                        ]);

                        // ✏️ Child item rows — keep Labels empty (or use $labelStr if you prefer)
                        foreach ($txnItems as $item) {
                            // A targeted-SKU multiple-purchase refund shows on the
                            // matching item row.
                            $itemIsRefundTarget = $refundOnItems && (
                                in_array((int) $item->id, $refundTargetItemIds, true)
                                || (filled($item->vend_channel_code)
                                    && in_array((string) $item->vend_channel_code, $refundTargetChannelCodes, true))
                            );
                            fputcsv($stream, [
                                $orderIdCell,
                                \Carbon\Carbon::parse($txn->transaction_datetime)->toDateTimeString(),
                                $txn->vend_code ?? '',
                                $txn->vend_prefix_name ?? '',
                                $txn->customer_id + 20000,
                                $txn->person_id ? $txn->virtual_customer_code : '',
                                $txn->customer_name,
                                (int) $item->vend_channel_code,
                                $item->product->code ?? '',
                                $item->product->name ?? '',
                                'P1',
                                '',
                                $item->vendChannel ? $item->vendChannel->amount / 100 : '',
                                $item->unitCost ? $item->unitCost->cost : '',
                                '',
                                '', // Cashless Mfg empty for item rows
                                $item->vendChannelError->code ?? '',
                                $txn->location_type_name,
                                $txn->operator_code,
                                '',
                                // Inherit the parent's refund flag so item rows of a
                                // refunded multiple-purchase get filtered out together
                                // with the parent (keeps Amount/Breakdown columns tallied).
                                $txn->is_refunded ? 'Yes' : '',
                                $txn->is_multiple ? 'Yes' : 'No',
                                0,
                                $txn->interface_type,
                                $txn_json['dcvend_user_id'] ?? '',
                                '',
                                '',
                                '', // Labels for item rows
                                $dispenseAttempted, // inherit parent's gateway dispense state
                                $itemIsRefundTarget ? $txn->refund_request_reference : '',
                                $itemIsRefundTarget ? ($txn->refund_request_status ?? '') : '',
                            ]);
                        }
                    }
                });

            // Append dispensed-but-unreported gateway revenue once (first part
            // only) so the combined zip tallies with the dashboard "Total Sales".
            if ((int) $this->chunkIndex === 0) {
                $this->appendUnreportedGatewayRows($stream, $request, $user);
            }

            rewind($stream);

            // Upload to DigitalOcean Spaces
            Storage::disk('digitaloceanspaces')->put($spacesPath, $stream, [
                'visibility' => 'public',
            ]);

            $url = Storage::disk('digitaloceanspaces')->url($spacesPath);

            $job->attachment()->create([
                'type' => 2,
                'file_name' => $filename,
                'full_url' => $url,
                'local_url' => $spacesPath,
            ]);

            $chunk->update([
                'status' => 'completed',
                'filename' => $filename,
            ]);

            // Check if all are done, then dispatch zipper
            $pending = ExportJobChunk::where('export_job_id', $this->jobId)
                ->where('status', '!=', 'completed')
                ->exists();

            if (!$pending) {
                ZipVendTransactionCsvExport::dispatch($this->jobId);
            }


        } catch (\Throwable $e) {
            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $chunk->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
