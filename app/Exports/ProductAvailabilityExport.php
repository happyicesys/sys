<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductAvailabilityExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithStyles
{
    protected $products;
    protected $planningDate;

    public function __construct($products, $planningDate)
    {
        $this->products = $products;
        $this->planningDate = $planningDate;
    }

    public function collection()
    {
        return $this->products;
    }

    public function headings(): array
    {
        return [
            '#',
            'Product Code',
            'Product Name',
            'Is Available',
            'Daily Sold Qty (avg 7d)',
            'Qty in Warehouse (API)',
            'Picked Qty (not yet sync)',
            'Remaining Qty',
            'To Pick Qty (' . $this->planningDate . ')',
            'Capped Qty per Channel',
        ];
    }

    public function map($product): array
    {
        static $index = 0;
        $index++;

        return [
            $index,
            $product->code,
            $product->name,
            $product->is_available ? 'Yes' : 'No',
            $product->avg_seven_days_count ?? 0,
            $product->qty_available_pcs_api ?? 0,
            $product->not_yet_sync_api_qty ?? 0,
            $product->net_available_qty_pcs_api ?? 0,
            $product->needed_qty ?? 0,
            $product->max_ops_job_pick_limit ?? 'No',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 15,
            'C' => 35,
            'D' => 14,
            'E' => 22,
            'F' => 22,
            'G' => 22,
            'H' => 18,
            'I' => 22,
            'J' => 22,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
