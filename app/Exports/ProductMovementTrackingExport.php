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
                    ELSE 'Unknown'
                END as type_label,
                product_movements.id as id,
                products.code as product_code,
                products.name as product_name,
                product_movements.qty as qty,
                product_movements.batch_number as remarks,
                users.name as by_user,
                product_movements.created_at as created_at,
                'ProductMovement' as source_type
            ")
            ->leftJoin('products', 'products.id', '=', 'product_movements.product_id')
            ->leftJoin('users', 'product_movements.user_id', '=', 'users.id')
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
                ops_jobs.date as date,
                'Outgoing' as type_label,
                ops_job_item_channels.id as id,
                products.code as product_code,
                products.name as product_name,
                (ops_job_item_channels.picked_qty * -1) as qty,
                (ops_job_items.id + 25000) as remarks,
                users.name as by_user,
                ops_job_items.picked_at as created_at,
                'OpsJob' as source_type
            ")
            ->join('ops_job_items', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
            ->join('ops_job_item_channels', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
            ->join('products', 'products.id', '=', 'ops_job_item_channels.product_id')
            ->leftJoin('users', 'ops_jobs.delivered_by', '=', 'users.id')
            ->whereIn('ops_jobs.operator_id', $operators)
            ->where('ops_job_items.status', '>=', 2)
            ->where('ops_job_items.status', '!=', 99)
            ->where('ops_job_item_channels.picked_qty', '>', 0)
            ->when($request->product_id, function ($q) use ($request) {
                $q->where('ops_job_item_channels.product_id', $request->product_id);
            })
            ->when($request->date_from, function ($q) use ($request) {
                $q->whereDate('ops_jobs.date', '>=', $request->date_from);
            })
            ->when($request->date_to, function ($q) use ($request) {
                $q->whereDate('ops_jobs.date', '<=', $request->date_to);
            });

        $query = $incomingQuery->union($outgoingQuery)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Date',
            'Type',
            'Product Code',
            'Product Name',
            'Qty',
            'Job Number',
            'By',
            'Created At',
        ];
    }

    public function map($row): array
    {
        return [
            Carbon::parse($row->date)->toDateString(),
            $row->type_label,
            $row->product_code,
            $row->product_name,
            $row->qty,
            $row->remarks,
            $row->by_user,
            $row->created_at ? Carbon::parse($row->created_at)->format('ymd h:i a') : '-',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 15,
            'C' => 15,
            'D' => 30,
            'E' => 10,
            'F' => 30,
            'G' => 15,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
