<?php

namespace App\Jobs;

use App\Models\Operator;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use App\Models\ExportJob;
use App\Models\Tag;
use App\Models\User;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportVendTransactionCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    protected $jobId;
    protected $requestData;
    protected $userID;

    public function __construct($jobId, array $requestData, $userID = null)
    {
        $this->jobId = $jobId;
        $this->requestData = $requestData;
        $this->userID = $userID;
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
                            Operator::where('code', 'DCVIC')->first()?->id,
                            Operator::where('code', 'HIESG')->first()?->id,
                            Operator::where('code', 'IP')->first()?->id,
                        ]
                    ]);
                } else {
                    $request->merge(['operators' => [$user->operator_id]]);
                }
            }

            $filename = 'vend_transactions_' . now()->format('Ymd_His') . '.csv';
            $spacesPath = "sys/exports/{$filename}";

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
                'Error Code',
                'Location Type',
                'Operator',
                'Payment Successful',
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
                ->filterTransactionIndex($request)
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
                    DB::raw('vend_transactions.label_json AS label_ids_json'),
                ])
                ->orderBy('vend_transactions.id')
                ->chunk(500, function ($transactions) use ($stream) {
                    $transactionIds = $transactions->pluck('id');

                    // Pull items for this chunk (unchanged)
                    $items = VendTransactionItem::with([
                        'vendChannel:id,code,amount',
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

                        // ✏️ Parent row — append $labelStr at the end
                        fputcsv($stream, [
                            $txn->order_id,
                            \Carbon\Carbon::parse($txn->transaction_datetime)->toDateTimeString(),
                            $txn->vend_code ?? '',
                            $txn->vend_prefix_name ?? '',
                            $txn->customer_id + 20000,
                            $txn->person_id ? $txn->virtual_customer_code : '',
                            $txn->customer_name,
                            $txn->vend_channel_code ?? '',
                            $txn->product_code,
                            $txn->vend_channel_code == 0 && !$txn->product_code ? 'Multiple Puchase' : $txn->product_name,
                            $txn->vend_channel_amount == $txn->amount ? 'P1' : ($txn->vend_channel_amount2 == $txn->amount ? 'P2' : ''),
                            $main_amount,
                            $multipleBreakdown,
                            $txn->cost ? $txn->cost / 100 : '',
                            $txn->payment_method_name,
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
                            fputcsv($stream, [
                                $txn->order_id,
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
                                $item->vendChannelError->code ?? '',
                                $txn->location_type_name,
                                $txn->operator_code,
                                '',
                                '',
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
