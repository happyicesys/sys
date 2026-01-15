<?php

namespace App\Exports;

use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\Product;
use App\Models\ProductMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductMovementTrackingExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithStyles
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $request = $this->request;

        $request->merge([
            'date_from' => $request->date_from ?: Carbon::today()->toDateString(),
            'date_to' => $request->date_to ?: Carbon::today()->toDateString(),
        ]);

        if (!$request->operators) {
            if (auth()->user()->operator->code == 'HIPL') {
                $operators = [
                    auth()->user()->operator_id,
                    Operator::where('code', 'HIMD')->first()?->id,
                    Operator::where('code', 'LEA')->first()?->id,
                    Operator::where('code', 'DCVIC')->first()?->id,
                    Operator::where('code', 'HIESG')->first()?->id,
                    Operator::where('code', 'IP')->first()?->id,
                ];
            } else {
                $operators = [auth()->user()->operator_id];
            }
        } else {
            $operators = is_array($request->operators) ? $request->operators : explode(',', $request->operators);
        }

        // Incoming Query
        $incomingQuery = ProductMovement::query()
            ->selectRaw("
                product_movements.created_at as date,
                CASE
                    WHEN type = 1 THEN 'Incoming'
                    WHEN type = 2 THEN 'Adjustment'
                    WHEN type = 3 THEN 'Picked'
                    WHEN type = 4 THEN 'Unpicked'
                    ELSE 'Unknown'
                END as type_label,
                product_movements.id as id,
                products.code as product_code,
                products.name as product_name,
                product_movements.qty as qty,
                product_movements.batch_number as remarks,
                users.name as by_user,
                product_movements.created_at as created_at,
                ops_jobs.date as job_delivery_date,
                'ProductMovement' as source_type
            ")
            ->leftJoin('products', 'products.id', '=', 'product_movements.product_id')
            ->leftJoin('users', 'product_movements.user_id', '=', 'users.id')
            ->leftJoin('ops_job_items', function ($join) {
                $join->on(DB::raw('product_movements.batch_number - 25000'), '=', 'ops_job_items.id')
                    ->whereIn('product_movements.type', [3, 4]);
            })
            ->leftJoin('ops_jobs', 'ops_job_items.ops_job_id', '=', 'ops_jobs.id')
            ->whereIn('product_movements.operator_id', $operators)
            ->when($request->product_id, function ($q) use ($request) {
                $q->where('product_movements.product_id', $request->product_id);
            })
            ->when($request->date_from, function ($q) use ($request) {
                $q->whereDate('product_movements.created_at', '>=', $request->date_from);
            })
            ->when($request->date_to, function ($q) use ($request) {
                $q->whereDate('product_movements.created_at', '<=', $request->date_to);
            });

        // Outgoing Query
        $outgoingQuery = OpsJob::query()
            ->selectRaw("
                COALESCE(ops_job_items.picked_at, ops_job_items.last_picked_at) as date,
                'Picked' as type_label,
                ops_job_item_channels.id as id,
                products.code as product_code,
                products.name as product_name,
                (CASE WHEN ops_job_item_channels.picked_qty > 0 THEN ops_job_item_channels.picked_qty ELSE ops_job_item_channels.saved_picked_qty END * -1) as qty,
                (ops_job_items.id + 25000) as remarks,
                users.name as by_user,
                COALESCE(ops_job_items.picked_at, ops_job_items.last_picked_at) as created_at,
                ops_jobs.date as job_delivery_date,
                'OpsJob' as source_type
            ")
            ->join('ops_job_items', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
            ->join('ops_job_item_channels', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
            ->join('products', 'products.id', '=', 'ops_job_item_channels.product_id')
            ->leftJoin('users', 'ops_jobs.delivered_by', '=', 'users.id')
            ->whereIn('ops_jobs.operator_id', $operators)
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('ops_job_items.status', '>=', 2)
                        ->where('ops_job_item_channels.picked_qty', '>', 0);
                })
                    ->orWhere(function ($sub) {
                        $sub->whereNotNull('ops_job_items.undo_picked_at')
                            ->whereNotNull('ops_job_items.last_picked_at')
                            ->where('ops_job_item_channels.saved_picked_qty', '>', 0);
                    });
            })
            ->where('ops_job_items.status', '!=', 99)
            ->when($request->product_id, function ($q) use ($request) {
                $q->where('ops_job_item_channels.product_id', $request->product_id);
            })
            ->when($request->date_from, function ($q) use ($request) {
                $q->whereDate(DB::raw('COALESCE(ops_job_items.picked_at, ops_job_items.last_picked_at)'), '>=', $request->date_from);
            })
            ->when($request->date_to, function ($q) use ($request) {
                $q->whereDate(DB::raw('COALESCE(ops_job_items.picked_at, ops_job_items.last_picked_at)'), '<=', $request->date_to);
            })
            // Exclude records after cutoff
            ->where(DB::raw('COALESCE(ops_job_items.picked_at, ops_job_items.last_picked_at)'), '<', '2026-01-15 19:30:00');

        // Undo Picked Query (OpsJobItemChannel -> OpsJobItem)
        $undoPickedQuery = OpsJob::query()
            ->selectRaw("
                ops_job_items.undo_picked_at as date,
                'Unpicked' as type_label,
                ops_job_item_channels.id as id,
                products.code as product_code,
                products.name as product_name,
                (ops_job_item_channels.saved_picked_qty) as qty,
                (ops_job_items.id + 25000) as remarks,
                users.name as by_user,
                ops_job_items.undo_picked_at as created_at,
                ops_jobs.date as job_delivery_date,
                'OpsJob' as source_type
            ")
            ->join('ops_job_items', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
            ->join('ops_job_item_channels', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
            ->join('products', 'products.id', '=', 'ops_job_item_channels.product_id')
            ->leftJoin('users', 'ops_job_items.undo_picked_by', '=', 'users.id')
            ->whereIn('ops_jobs.operator_id', $operators)
            ->whereNotNull('ops_job_items.undo_picked_at')
            ->where('ops_job_item_channels.saved_picked_qty', '>', 0)
            ->when($request->product_id, function ($q) use ($request) {
                $q->where('ops_job_item_channels.product_id', $request->product_id);
            })
            ->when($request->date_from, function ($q) use ($request) {
                $q->whereDate('ops_job_items.undo_picked_at', '>=', $request->date_from);
            })
            ->when($request->date_to, function ($q) use ($request) {
                $q->whereDate('ops_job_items.undo_picked_at', '<=', $request->date_to);
            })
            // Exclude records after cutoff
            ->where('ops_job_items.undo_picked_at', '<', '2026-01-15 19:30:00');

        $query = $incomingQuery->union($outgoingQuery)->union($undoPickedQuery)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Date',
            'Time',
            'Movement',
            'Product Code',
            'Product Name',
            'Qty',
            'By',
            'Job Number',
            'Job Delivery Date',
        ];
    }

    public function map($row): array
    {
        return [
            Carbon::parse($row->date)->toDateString(),
            Carbon::parse($row->date)->format('h:i a'),
            $row->type_label,
            $row->product_code,
            $row->product_name,
            $row->qty,
            $row->by_user,
            $row->remarks ? '#' . $row->remarks : '',
            $row->job_delivery_date ? Carbon::parse($row->job_delivery_date)->toDateString() : '',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 15,
            'C' => 15, // shifted
            'D' => 15,
            'E' => 30,
            'F' => 10,
            'G' => 15,
            'H' => 30,
            'I' => 20,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
