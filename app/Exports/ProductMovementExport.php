<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductMovementExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithStyles
{
    protected $products;

    public function __construct($products)
    {
        $this->products = $products;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->products;
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
            'Remarks',
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
            $product->remarks ?? '',
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
            'H' => 30,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
