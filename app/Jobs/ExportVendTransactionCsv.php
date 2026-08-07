<?php

namespace App\Jobs;

use App\Jobs\Concerns\AppendsUnreportedGatewayCsvRows;
use App\Models\Operator;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Models\ExportJob;
use App\Models\Tag;
use App\Models\User;
use App\Support\ProductAccess;
use App\Support\TransactionAccess;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportVendTransactionCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, AppendsUnreportedGatewayCsvRows;

    public $tries = 1;

    protected $jobId;
    protected $requestData;
    protected $userID;

    /**
     * "Access Product(s)" allow-list, resolved ONCE in the controller.
     *
     * auth() is empty inside a queue worker, so the global scope cannot fire
     * here - the set has to be handed in. Resolving it in the controller also
     * means a pivot edit mid-export cannot split a multi-chunk run.
     *
     * null = unrestricted. [] = restricted to nothing (export zero rows).
     *
     * @var array<int, int>|null
     */
    protected $allowedProductIds;

    /**
     * "Transaction Access From" - earliest sales date this viewer may export.
     *
     * Resolved ONCE in the controller for exactly the reasons the product
     * allow-list above is: auth() is empty in a worker so the global scope
     * cannot fire, and resolving once stops an edit mid-export from splitting a
     * multi-chunk run. null = unrestricted.
     *
     * @var string|null
     */
    protected $transactionAccessFrom;

    // $allowedProductIds is deliberately REQUIRED (no default), matching
    // ExportVendTransactionCsvChunk: a dispatch site that forgets it must fail
    // loudly rather than silently export every product.
    public function __construct($jobId, array $requestData, $userID, ?array $allowedProductIds, ?string $transactionAccessFrom)
    {
        $this->jobId = $jobId;
        $this->requestData = $requestData;
        $this->userID = $userID;
        $this->allowedProductIds = $allowedProductIds;
        $this->transactionAccessFrom = $transactionAccessFrom;
    }

    /**
     * Blank the Unit Cost cell for a product the viewer may not access.
     */
    protected function maskedUnitCost($productId, $value)
    {
        return ProductAccess::allows($this->allowedProductIds, $productId) ? $value : '';
    }

    /**
     * May the viewer see this basket item's own detail?
     *
     * The basket itself is still exported WHOLE - the item row stays, carrying
     * its channel number. What a foreign item loses is everything identifying:
     * product code, product name, price type, amount breakdown and unit cost.
     *
     * CONSEQUENCE, deliberate: for a restricted viewer the Amount Breakdown
     * column no longer sums to the parent Amount on a mixed basket. Verified on
     * live txn 5902087 - header $10.90 = 3.90 (theirs) + 3.50 + 3.50 (not);
     * a viewer restricted to the first product now sees 3.90 and two blanks.
     * What is removed is the per-item ATTRIBUTION - which foreign product cost
     * what. The 7.00 aggregate is still derivable from the parent row, and
     * deliberately so: the header Amount is what makes a future basket-level
     * discount explicable, which is the whole reason mixed baskets are shown.
     * So the column-sums-to-parent property holds only for UNRESTRICTED
     * exports; those are byte-identical to before.
     *
     * This is what the transaction table already does on screen. It gets there
     * a different way - Product carries ProductAccessProductScope, so the
     * eager-loaded relation simply comes back null for a foreign product - but
     * that mechanism is INERT here: a queued job has no auth()->user(), which
     * is exactly why this class is handed $allowedProductIds explicitly. Hence
     * the same decision has to be made by hand on this side.
     */
    protected function allowsItem($item): bool
    {
        return ProductAccess::allowsItem($this->allowedProductIds, $item);
    }

    public function handle()
    {
        $job = ExportJob::find($this->jobId);
        if (!$job)
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

            $filename = 'vend_transactions_' . now()->format('Ymd_His') . '.csv';
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
                'Campaign Labels'
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
                ->when($user->vends()->exists(), function ($query) use ($user) {
                    $query->whereIn('vend_transactions.vend_id', $user->vends->pluck('id'));
                })
                // "Access Product(s)": a basket is exported whole when ANY of its
                // items is allowed. The other party's item rows survive carrying
                // only their channel number - product code, name, price type,
                // amount breakdown and unit cost are all blanked further down.
                ->tap(fn($query) => ProductAccess::applyToVendTransactions($query, $this->allowedProductIds))
                // "Transaction Access From": same hand-in reason as the product
                // allow-list directly above - TransactionAccessScope cannot fire
                // in a worker, so the resolved date has to travel with the job.
                ->tap(fn($query) => TransactionAccess::applyToColumn($query, 'vend_transactions.transaction_datetime', $this->transactionAccessFrom))
                ->filterTransactionIndex($request)
                // Mirror the aggregate-cards query (VendController@transactionIndex):
                // only settled sales (excludes in-flight PENDING and voided REFUNDED
                // gateway rows) and no testing machines. Without these the CSV
                // contains rows the "Total Sales" card never counts, so the
                // exported total comes out higher than the dashboard.
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
                    'vend_channels.product_id AS vend_channel_product_id',
                    'vend_channels.amount AS vend_channel_amount',
                    'vend_channels.amount2 AS vend_channel_amount2',
                    'vend_channel_errors.code AS vend_channel_error_code',
                    DB::raw('vend_transactions.label_json AS label_ids_json'),
                ])
                ->orderBy('vend_transactions.id')
                ->chunk(500, function ($transactions) use ($stream) {
                    $transactionIds = $transactions->pluck('id');

                    // Pull items for this chunk (unchanged)
                    $items = VendTransactionItem::with([
                        // product_id: needed by ProductAccess::itemProductId() as the
                        // fallback when the item row carries no product of its own.
                        'vendChannel:id,code,amount,product_id',
                        'product:id,code,name',
                        'unitCost:id,cost',
                        'vendChannelError:id,code,desc',
                    ])->whereIn('vend_transaction_id', $transactionIds)
                        ->get()
                        ->groupBy('vend_transaction_id');

                    // Collect all label values (ints and strings) across this chunk
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

                    // Fetch tags by id or by name/slug
                    $tagsById = Tag::whereIn('id', $tagIds)
                        ->get(['id', 'name', 'slug'])
                        ->keyBy('id');

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

                        // Build labels string honoring provided order
                        $labelStr = collect($vals)->map(function ($v) use ($tagsById, $tagsByNameSlug) {
                            if (is_int($v) || (is_string($v) && ctype_digit($v))) {
                                $t = $tagsById->get((int) $v);
                            } else {
                                $t = $tagsByNameSlug[$v] ?? null;
                            }
                            return $t->name ?? $t->slug ?? (string) $v;
                        })->implode(', ');

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
                            ? ($txn->amount - $txnItems->sum(fn($it) => $it->vendChannel?->amount ?? 0)) / 100
                            : $main_amount;

                        // Wrap order_id in Excel text-formula so long numeric IDs
                        // do not get converted to scientific notation when the
                        // CSV is opened directly in Excel.
                        $orderIdCell = $txn->order_id !== null && $txn->order_id !== ''
                            ? '="' . $txn->order_id . '"'
                            : '';

                        // ✏️ Parent row — append $labelStr at the end
                        fputcsv($stream, [
                            $orderIdCell,
                            \Carbon\Carbon::parse($txn->transaction_datetime)->toDateTimeString(),
                            $txn->vend_code ?? '',
                            $txn->vend_prefix_name ?? '',
                            $txn->customer_id + 20000,
                            $txn->customer_id + 20000,
                            $txn->customer_name,
                            $txn->vend_channel_code ?? '',
                            $txn->product_code,
                            $txn->vend_channel_code == 0 && !$txn->product_code ? 'Multiple Purchase' : $txn->product_name,
                            $txn->vend_channel_amount == $txn->amount ? 'P1' : ($txn->vend_channel_amount2 == $txn->amount ? 'P2' : ''),
                            $main_amount,
                            $multipleBreakdown,
                            $this->maskedUnitCost(
                                $txn->product_id ?: $txn->vend_channel_product_id,
                                $txn->cost ? $txn->cost / 100 : ''
                            ),
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
                        ]);

                        // ✏️ Child item rows — keep Labels empty (or repeat $labelStr if you prefer)
                        foreach ($txnItems as $item) {
                            // "Access Product(s)": see allowsItem(). The row is still
                            // written - only its product identity and money are dropped.
                            $itemAllowed = $this->allowsItem($item);

                            fputcsv($stream, [
                                $orderIdCell,
                                \Carbon\Carbon::parse($txn->transaction_datetime)->toDateTimeString(),
                                $txn->vend_code ?? '',
                                $txn->vend_prefix_name ?? '',
                                $txn->customer_id + 20000,
                                $txn->customer_id + 20000,
                                $txn->customer_name,
                                // Channel stays: the table shows it too, and it is the
                                // machine's slot number, not a product.
                                (int) $item->vend_channel_code,
                                $itemAllowed ? ($item->product->code ?? '') : '',
                                $itemAllowed ? ($item->product->name ?? '') : '',
                                $itemAllowed ? 'P1' : '',
                                '',
                                $itemAllowed
                                    ? ($item->vendChannel ? $item->vendChannel->amount / 100 : '')
                                    : '',
                                // Same predicate as every other cell on this row.
                                // maskedUnitCost() stays for the PARENT row, which
                                // resolves its product differently.
                                $itemAllowed ? ($item->unitCost ? $item->unitCost->cost : '') : '',
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
                                '', // 👈 Labels for item row (leave empty or put $labelStr)
                            ]);
                        }
                    }
                });

            // Append dispensed-but-unreported gateway revenue so the CSV total
            // tallies with the dashboard "Total Sales" (from the cutoff onward).
            $this->appendUnreportedGatewayRows($stream, $request, $user, $this->allowedProductIds, $this->transactionAccessFrom);

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

            $job->update([
                'status' => 'completed',
                'filename' => $filename,
            ]);

        } catch (\Throwable $e) {
            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
