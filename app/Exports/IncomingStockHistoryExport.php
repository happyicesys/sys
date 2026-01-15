<?php

namespace App\Exports;

use App\Models\ProductMovement;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IncomingStockHistoryExport implements FromQuery, WithHeadings, WithMapping, WithColumnWidths, WithStyles
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function query()
    {
        $query = ProductMovement::query()
            ->with(['product', 'user', 'operator'])
            ->where('type', ProductMovement::TYPE_INCOMING)
            ->whereNotNull('batch_number');

        if ($this->request->has('date') && $this->request->date) {
            $query->whereDate('created_at', $this->request->date);
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'Batch Number',
            'Date',
            'Time',
            'Input By',
            'Product Code',
            'Product Name',
            'Qty',
            'Remarks',
        ];
    }

    public function map($movement): array
    {
        $inputBy = $movement->user ? $movement->user->name : ($movement->operator ? $movement->operator->name : '-');

        return [
            $movement->batch_number,
            $movement->created_at->format('Y-m-d'),
            $movement->created_at->format('h:i A'),
            $inputBy,
            $movement->product ? $movement->product->code : '-',
            $movement->product ? $movement->product->name : '-',
            $movement->qty,
            $movement->remarks,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 15,
            'C' => 15,
            'D' => 20,
            'E' => 15,
            'F' => 30,
            'G' => 10,
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
