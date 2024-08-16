<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorResource;
use App\Http\Resources\OpsJobResource;
use App\Http\Resources\OpsJobItemResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendResource;
use App\Jobs\SyncOpsJobItemTransactionItemCMS;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannelRecord;
use App\Models\VendData;
use App\Models\VendTransaction;
use App\Traits\GetUserTimezone;
use App\Services\OpsJobService;
use App\Services\RunningNumberService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OpsJobController extends Controller
{
    use GetUserTimezone;

    public function __construct()
    {
        $this->middleware('auth');
        $this->opsJobService = new OpsJobService();
        $this->runningNumberService = new RunningNumberService();
    }

    public function index(Request $request)
    {
        $request->merge([
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'date',
            'sortBy' => $request->sortBy ? $request->sortBy : false,
            'date_from' => $request->date_from ? Carbon::parse($request->date_from)->setTimezone($this->getUserTimezone())->startOfDay() : Carbon::today()->setTimezone($this->getUserTimezone())->startOfDay(),
            'date_to' => $request->date_to ? Carbon::parse($request->date_to)->setTimezone($this->getUserTimezone())->endOfDay() : Carbon::today()->addWeek()->setTimezone($this->getUserTimezone())->endOfDay(),
        ]);

        $opsJobs = OpsJob::query()
            ->with(['createdBy', 'deliveredBy', 'operator', 'pickedBy', 'updatedBy'])
            ->selectRaw('ops_jobs.*,
                (SELECT COUNT(*) FROM ops_job_items WHERE ops_job_items.ops_job_id = ops_jobs.id) as ops_job_items_count')
            ->selectRaw('(SELECT COUNT(*) FROM ops_job_items WHERE ops_job_items.ops_job_id = ops_jobs.id AND ops_job_items.status >= ? AND ops_job_items.status <> ?) as ops_job_items_delivered_count', [OpsJob::STATUS_DELIVERED, OpsJob::STATUS_CANCELLED])
            ->selectRaw('(SELECT COUNT(*) FROM ops_job_items WHERE ops_job_items.ops_job_id = ops_jobs.id AND ops_job_items.status = ?) as ops_job_items_verified_count', [OpsJob::STATUS_VERIFIED])
            ->selectRaw('
                IFNULL(
                    ((SELECT COUNT(*) FROM ops_job_items WHERE ops_job_items.ops_job_id = ops_jobs.id AND ops_job_items.status >= ? AND ops_job_items.status <> ?) / (SELECT COUNT(*) FROM ops_job_items WHERE ops_job_items.ops_job_id = ops_jobs.id)) * 100,
                    0
                ) as ops_job_items_delivered_count_percentage', [OpsJob::STATUS_DELIVERED, OpsJob::STATUS_CANCELLED])
            ->selectRaw('
                IFNULL(
                    ((SELECT COUNT(*) FROM ops_job_items WHERE ops_job_items.ops_job_id = ops_jobs.id AND ops_job_items.status = ?) / (SELECT COUNT(*) FROM ops_job_items WHERE ops_job_items.ops_job_id = ops_jobs.id)) * 100,
                    0
                ) as ops_job_items_verified_count_percentage', [OpsJob::STATUS_VERIFIED])
            ->selectRaw('
                (SELECT SUM(ops_job_item_channels.actual_qty * vend_channels.amount)
                FROM ops_job_item_channels
                JOIN vend_channels ON vend_channels.id = ops_job_item_channels.vend_channel_id
                JOIN ops_job_items ON ops_job_items.id = ops_job_item_channels.ops_job_item_id
                WHERE ops_job_items.ops_job_id = ops_jobs.id
                AND ops_job_items.status >= ?
                AND ops_job_items.status <> ?
                ) as stock_in_amount', [OpsJob::STATUS_DELIVERED, OpsJob::STATUS_CANCELLED])
            ->selectRaw('
                (SELECT SUM(ops_job_item_channels.actual_qty)
                FROM ops_job_item_channels
                JOIN vend_channels ON vend_channels.id = ops_job_item_channels.vend_channel_id
                JOIN ops_job_items ON ops_job_items.id = ops_job_item_channels.ops_job_item_id
                WHERE ops_job_items.ops_job_id = ops_jobs.id
                AND ops_job_items.status >= ?
                AND ops_job_items.status <> ?
                ) as stock_in_count', [OpsJob::STATUS_DELIVERED, OpsJob::STATUS_CANCELLED])
            ->selectRaw('(
                SELECT SUM(ops_job_items.cash_amount)
                FROM ops_job_items
                WHERE ops_job_items.ops_job_id = ops_jobs.id
                ) as total_cash_amount')
            ->selectRaw('(
                SELECT SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(vend_channel_records.before_statis_json, "$.CashAmt")) AS DECIMAL(10, 2)))
                FROM ops_job_items
                JOIN vend_channel_records ON vend_channel_records.id = ops_job_items.vend_channel_record_id
                WHERE ops_job_items.ops_job_id = ops_jobs.id
                ) as total_cash_amount_from_vmc')
            ->selectRaw('(
                SELECT SUM(ops_job_items.acc_total_amount)
                FROM ops_job_items
                WHERE ops_job_items.ops_job_id = ops_jobs.id
                ) as acc_vend_transactions_amount')
            ->selectRaw('(
                SELECT SUM(ops_job_items.acc_total_count)
                FROM ops_job_items
                WHERE ops_job_items.ops_job_id = ops_jobs.id
                ) as acc_vend_transactions_count')
            ->when($request->code, function($query, $search) {
                $query->where('code', 'LIKE', "%{$search}%");
            })
            ->when($request->date_from, function($query, $search) {
                $query->where('date', '>=', $search);
            })
            ->when($request->date_to, function($query, $search) {
                $query->where('date', '<=', $search);
            })
            ->when($request->delivered_by, function($query, $search) {
                $query->where('delivered_by', $search);
            })
            ->when($request->created_by, function($query, $search) {
                $query->where('created_by', $search);
            })
            ->when($request->sortKey, function($query, $search) use ($request) {
                if (in_array($search, [
                    'ops_job_items_count',
                    'ops_job_items_delivered_count',
                    'ops_job_items_verified_count',
                    'ops_job_items_delivered_count_percentage',
                    'ops_job_items_verified_count_percentage',
                    'total_cash_amount_from_vmc' // Adding this to the list of sortable columns
                ])) {
                    $query->orderByRaw("{$search} " . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'));
                } else {
                    $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                }
            })
            ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
            ->withQueryString();




        return Inertia::render('OpsJob/Index', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'opsJobs' => OpsJobResource::collection(
                $opsJobs
            ),
            'userOptions' => UserResource::collection(
                User::orderBy('name')->get()
            ),
        ]);
    }

    public function confirmItem(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        switch($opsJobItem->status) {
            case 1:
                $opsJobItem->update([
                    'status' => OpsJob::STATUS_PICKED,
                    'picked_by' => auth()->id(),
                    'picked_at' => Carbon::now(),
                ]);

                if($request->channels) {
                    foreach($request->channels as $channel) {
                        $opsJobItemChannel = $opsJobItem->opsJobItemChannels->where('id', $channel['id'])->first();
                        $opsJobItemChannel->update([
                            'picked_qty' => $channel['picked'],
                        ]);
                    }
                }
                break;
            case 2:
                $opsJobItem->update([
                    'status' => OpsJob::STATUS_DELIVERED,
                    'completed_by' => auth()->id(),
                    'completed_at' => Carbon::now(),
                    'cash_amount' => $request->cash_amount,
                    'cashless_amount' => $request->cashless_amount,
                ]);

                SyncOpsJobItemTransactionItemCMS::dispatch($opsJobItem->id);

                if($request->channels) {
                    foreach($request->channels as $channel) {
                        $opsJobItemChannel = $opsJobItem->opsJobItemChannels->where('id', $channel['id'])->first();
                        $opsJobItemChannel->update([
                            'actual_qty' => $channel['refill'],
                        ]);
                    }
                }

                // get previous opsJobItem of same vend, then return the completed_at
                $previousOpsJobItem = OpsJobItem::where('vend_id', $opsJobItem->vend_id)
                    ->where('id', '<', $opsJobItem->id)
                    ->where('status', '>=', OpsJob::STATUS_DELIVERED)
                    ->where('status', '<>', OpsJob::STATUS_CANCELLED)
                    ->orderBy('id', 'desc')
                    ->first();

                if($previousOpsJobItem) {
                    $vendTransactions = VendTransaction::query()
                        ->where('created_at', '>=', $previousOpsJobItem->completed_at)
                        ->where('created_at', '<=', $opsJobItem->completed_at)
                        ->where('vend_id', $opsJobItem->vend_id)
                        ->isSuccessful()
                        ->selectRaw('SUM(amount) as total_amount')
                        ->selectRaw('COUNT(*) as total_count')
                        ->first();

                    $opsJobItem->update([
                        'previous_ops_job_item_id' => $previousOpsJobItem->id,
                        'acc_total_amount' => $vendTransactions->total_amount,
                        'acc_total_count' => $vendTransactions->total_count,
                    ]);
                }

                $vendChannelRecord = VendChannelRecord::query()
                    ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, before_data_created_at, ?))', [$opsJobItem->completed_at])
                    ->where('vend_id', $opsJobItem->vend_id)
                    ->first();

                if($vendChannelRecord) {
                    $opsJobItem->update([
                        'vend_channel_record_id' => $vendChannelRecord->id,
                    ]);

                    if($vendChannelRecord->before_data_json || $vendChannelRecord->after_data_json) {
                        $opsJobItem->opsJobItemChannels->each(function($opsJobItemChannel) use ($vendChannelRecord) {
                            if($vendChannelRecord->before_data_json) {
                                $channels = $vendChannelRecord->before_data_json['channels'] ?? [];

                                foreach ($channels as $channel) {
                                    if (isset($channel['channel_code']) && $channel['channel_code'] == $opsJobItemChannel->vend_channel_code) {
                                        $opsJobItemChannel->update([
                                            'vmc_before_qty' => $channel['qty'], // Update with the 'qty' value from the matched channel
                                        ]);
                                        break; // Exit the loop once the matching channel is found
                                    }
                                }
                            }

                            if($vendChannelRecord->after_data_json) {
                                if ($vendChannelRecord->after_data_json) {
                                    $channels = $vendChannelRecord->after_data_json['channels'] ?? [];

                                    foreach ($channels as $channel) {
                                        if (isset($channel['channel_code']) && $channel['channel_code'] == $opsJobItemChannel->vend_channel_code) {
                                            $opsJobItemChannel->update([
                                                'vmc_after_qty' => $channel['qty'], // Update with the 'qty' value from the matched channel
                                            ]);
                                            break; // Exit the loop once the matching channel is found
                                        }
                                    }
                                }
                            }
                        });
                    }
                }

                break;
        }

        return redirect()->back();
    }

    public function createCmsEmptyInvoices($id)
    {
        $opsJob = OpsJob::findOrFail($id);

        foreach($opsJob->opsJobItems as $opsJobItem) {
            if($opsJobItem->cms_transaction_id) {
                continue;
            }

            $dataArr[] = [
                'ops_job_item_id' => $opsJobItem->id,
                'customer_id' => $opsJobItem->customer_id,
                'person_id' => $opsJobItem->customer?->person_id,
            ];
        }

        $this->opsJobService->createCMSEmptyInvoicesByOpsJobItem($dataArr, $opsJob->date, $opsJob->deliveredBy);

        return redirect()->back();
    }

    public function edit(Request $request, $id)
    {
        $opsJob = OpsJob::query()
        ->with([
            'createdBy:id,name', // Select only necessary columns
            'deliveredBy:id,name',
            'operator:id,name',
            'opsJobItems' => function($query) use ($request) {
                $query->when($request->vend_code, function($query, $search) {
                    $query->whereHas('vend', function($query) use ($search) {
                        $query->where('code', 'LIKE', "{$search}%");
                    });
                });
                $query->when($request->customer, function($query, $search) {
                    $query->where(function($query) use ($search) {
                        $query->whereHas('vend.customer', function($query) use ($search) {
                            $query->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('virtual_customer_code', 'LIKE', "{$search}%");
                        });
                    });
                });

                // Select necessary columns
                $query->select([
                    'id',
                    'cash_amount',
                    'cashless_amount',
                    'ops_job_id',
                    'vend_id',
                    'cms_transaction_id',
                    'sequence',
                    'status',
                    'picked_at',
                    'picked_by',
                    'completed_at',
                    'completed_by',
                    'remarks'
                ]);

                // Adjust the selectRaw queries to correctly reference the opsJobItems relationship
                $query->selectRaw('
                    (SELECT SUM(ops_job_item_channels.actual_qty * vend_channels.amount)
                     FROM ops_job_item_channels
                     JOIN vend_channels ON vend_channels.id = ops_job_item_channels.vend_channel_id
                    WHERE ops_job_item_channels.ops_job_item_id = ops_job_items.id
                     AND ops_job_items.status >= ?
                     AND ops_job_items.status <> ?
                    ) as stock_in_amount', [OpsJob::STATUS_DELIVERED, OpsJob::STATUS_CANCELLED]);

                $query->selectRaw('
                    (SELECT SUM(ops_job_item_channels.actual_qty)
                     FROM ops_job_item_channels
                     JOIN vend_channels ON vend_channels.id = ops_job_item_channels.vend_channel_id
                     WHERE ops_job_item_channels.ops_job_item_id = ops_job_items.id
                     AND ops_job_items.status >= ?
                     AND ops_job_items.status <> ?
                    ) as stock_in_count', [OpsJob::STATUS_DELIVERED, OpsJob::STATUS_CANCELLED]);

                $query->selectRaw('(
                    SELECT SUM(oj_items.cash_amount)
                    FROM ops_job_items oj_items
                    WHERE oj_items.id = ops_job_items.id
                ) as total_cash_amount');

                $query->selectRaw('(
                    SELECT SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(vend_channel_records.before_statis_json, "$.CashAmt")) AS DECIMAL(10, 2)))
                    FROM ops_job_items oj_items
                    JOIN vend_channel_records ON vend_channel_records.id = oj_items.vend_channel_record_id
                    WHERE oj_items.id = ops_job_items.id
                ) as total_cash_amount_from_vmc');

                $query->selectRaw('(
                    SELECT SUM(oj_items.acc_total_amount)
                    FROM ops_job_items oj_items
                    WHERE oj_items.id = ops_job_items.id
                ) as acc_vend_transactions_amount');

                $query->selectRaw('(
                    SELECT SUM(oj_items.acc_total_count)
                    FROM ops_job_items oj_items
                    WHERE oj_items.id = ops_job_items.id
                ) as acc_vend_transactions_count');
            },
            'opsJobItems.vend:id,customer_id,code,vend_prefix_id',
            'opsJobItems.vend.customer:id,name,person_id,virtual_customer_prefix,virtual_customer_code,ops_note',
            'opsJobItems.opsJobItemChannels.vendChannel.product.thumbnail',
            'opsJobItems.vend.vendPrefix',
            'opsJobItems.pickedBy:id,name',
            'opsJobItems.completedBy:id,name',
            'updatedBy:id,name'
        ])
        ->findOrFail($id);

        // dd($opsJob->toArray());


        $unbindedVendOptions = Vend::query()
            ->select(['id', 'customer_id', 'operator_id', 'code']) // Select necessary columns
            ->with(['customer:id,name']) // Select necessary columns
            ->has('customer')
            ->where('operator_id', $opsJob->operator_id)
            ->whereDoesntHave('opsJobItems', function($query) use ($opsJob) {
                $query->where('ops_job_id', $opsJob->id);
            })
            ->get();

        return Inertia::render('OpsJob/Edit', [
            'opsJob' => new OpsJobResource($opsJob),
            'unbindedVendOptions' => VendResource::collection($unbindedVendOptions),
        ]);
    }

    public function assign(Request $request)
    {
        $vendsID = $request->vends_id;
        $driverID = $request->driver_id;
        $date = $request->date;

        $opsJob = OpsJob::updateOrCreate([
            'date' => $date,
            'delivered_by' => $driverID,
        ], [
            'code' => $this->runningNumberService->getRunningCode(new OpsJob()),
            'created_by' => auth()->id(),
            'operator_id' => auth()->user()->operator_id,
            'updated_by' => auth()->id(),
            'updated_at' => null,
        ]);

        foreach($vendsID as $vendID) {
            $this->createOpsJobItem($opsJob->id, $vendID);
        }
    }

    public function createItem(Request $request, $id)
    {
        $this->createOpsJobItem($id, $request->vend_id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required',
            'delivered_by' => 'required',
            'operator_id' => 'required',
        ]);

        $opsJob = OpsJob::create([
            'code' => $this->runningNumberService->getRunningCode(new OpsJob()),
            'created_by' => auth()->id(),
            'date' => $request->date,
            'delivered_by' => $request->delivered_by,
            'operator_id' => $request->operator_id,
            'updated_by' => null,
            'updated_at' => null,
        ]);

        return redirect()->route('ops-jobs');
    }

    public function delete($id)
    {
        $opsJob = OpsJob::findOrFail($id);

        if($opsJob->opsJobItems) {
            foreach($opsJob->opsJobItems as $opsJobItem) {
                if($opsJobItem->opsJobItemChannels) {
                    $opsJobItem->opsJobItemChannels()->delete();
                }
            }

            $opsJob->opsJobItems()->delete();
        }
        $opsJob->delete();

        return redirect()->route('ops-jobs');
    }

    public function deleteItem($id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        if($opsJobItem->cms_transaction_id) {
            $this->opsJobService->deleteJobItemCMSTransaction($id);
        }

        if($opsJobItem->opsJobItemChannels) {
            $opsJobItem->opsJobItemChannels()->delete();
        }

        $opsJobItem->delete();

        return redirect()->back();
    }

    public function syncOpsJobItem(Request $request, $opsJobItemID)
    {
        $opsJobItem = OpsJobItem::findOrFail($opsJobItemID);

        $opsJobItem->update([
            'sequence' => $request->sequence,
        ]);

        return redirect()->back();
    }

    public function updateItem(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        $opsJobItem->update([
            'cash_amount' => $request->cash_amount,
            'cashless_amount' => $request->cashless_amount,
            'remarks' => $request->remarks,
        ]);

        return redirect()->back();
    }

    public function verifyItem(Request $request, $id)
    {
        $opsJobItem = OpsJobItem::findOrFail($id);

        switch($request->verify) {
            case 0:
                $opsJobItem->update([
                    'status' => OpsJob::STATUS_FLAGGED,
                ]);
                break;
            case 1:
                $opsJobItem->update([
                    'status' => OpsJob::STATUS_VERIFIED,
                ]);
                break;
        }

        return redirect()->back();
    }

    private function createOpsJobItem($opsJobID, $vendID)
    {
        $vend = Vend::with('vendChannels')->find($vendID);

        $opsJobItem = OpsJobItem::updateOrCreate([
            'customer_id' => $vend->customer_id,
            'ops_job_id' => $opsJobID,
            'vend_id' => $vendID,
        ],[
            'cash_amount' => 0,
            'cashless_amount' => 0,
            'status' => '1',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        foreach($vend->vendChannels as $vendChannel) {
            // dd($vendChannel->toArray());
            $opsJobItem->opsJobItemChannels()->updateOrCreate([
                'ops_job_id' => $opsJobItem->ops_job_id,
                'product_id' => $vendChannel->product_id ?? 0,
                'vend_channel_code' => $vendChannel->code,
                'vend_channel_id' => $vendChannel->id,
                'vend_code' => $vend->code,
            ],[
                'actual_qty' => 0,
                'capacity' => $vendChannel->capacity,
                'picked_qty' => 0,
            ]);
        }

        // sync next invoice date and next invoice driver
        $vend->customer->update([
            'next_invoice_date' => $opsJobItem->opsJob->date,
            'next_invoice_driver_id' => $opsJobItem->opsJob->delivered_by,
        ]);
    }
}
