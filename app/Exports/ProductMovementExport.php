<?php

namespace App\Exports;

use App\Models\Action;
use App\Models\Operator;
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

class ProductMovementExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithStyles
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

        // Replicating logic from ProductMovementController@index
        if ($request->operators == null) {
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
            if (in_array('all', $operators)) {
                $operators = Operator::all()->pluck('id')->toArray();
            }
        }

        $query = Product::query();

        $query->select(
            'products.id',
            'products.code',
            'products.name',
            'products.is_available',
            'products.thumbnail_url',
            'products.unit_cost',
            'products.operator_id',
            'products.max_ops_job_pick_limit'
        );

        if ($request->product_code) {
            $query->where('products.code', 'LIKE', '%' . $request->product_code . '%');
        }

        if ($request->product_name) {
            $query->where('products.name', 'LIKE', '%' . $request->product_name . '%');
        }

        if ($request->is_available && $request->is_available != 'all') {
            $query->where('products.is_available', $request->is_available == 'true' ? 1 : 0);
        }

        $query->leftJoin('product_movements', function ($join) use ($operators) {
            $join->on('products.id', '=', 'product_movements.product_id')
                ->whereIn('product_movements.operator_id', $operators);
        })
            ->leftJoin('ops_job_item_channels', function ($join) {
                $join->on('products.id', '=', 'ops_job_item_channels.product_id');
            })
            ->leftJoin('ops_job_items', function ($join) {
                $join->on('ops_job_item_channels.ops_job_item_id', '=', 'ops_job_items.id')
                    ->where('ops_job_items.status', '>=', 3)
                    ->where('ops_job_items.status', '!=', 99);
            })
            ->leftJoin('ops_jobs', function ($join) use ($operators) {
                $join->on('ops_job_items.ops_job_id', '=', 'ops_jobs.id')
                    ->whereIn('ops_jobs.operator_id', $operators);
            });

        $query->groupBy('products.id', 'products.code', 'products.name', 'products.is_available', 'products.thumbnail_url', 'products.unit_cost', 'products.operator_id', 'products.max_ops_job_pick_limit');

        $query->selectRaw('
            COALESCE(SUM(product_movements.qty), 0) as total_movements_qty,
            COALESCE(SUM(ops_job_item_channels.picked_qty), 0) as total_delivered_qty
        ');

        // Order logic similar to controller
        $query->orderBy('products.code', 'asc');

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Code',
            'Name',
            'Is Available',
            'Total Incoming',
            'Total Delivered',
            'Calculated Warehouse Qty',
        ];
    }

    public function map($product): array
    {
        $calculatedWarehouseQty = $product->total_movements_qty - $product->total_delivered_qty;

        return [
            $product->id,
            $product->code,
            $product->name,
            $product->is_available ? 'Yes' : 'No',
            $product->total_movements_qty ?? 0,
            $product->total_delivered_qty ?? 0,
            $calculatedWarehouseQty ?? 0,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 15,
            'C' => 30,
            'D' => 15,
            'E' => 15,
            'F' => 15,
            'G' => 20,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
