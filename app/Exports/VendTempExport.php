<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Illuminate\Contracts\View\View;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Http\Resources\VendTempResource;

class VendTempExport implements FromView, WithColumnFormatting, WithStyles
{
    use Exportable;

    private $vendTemps;

    public function __construct($vendTemps)
    {
        $this->vendTemps = $vendTemps;
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_NUMBER_0,
        ];
    }

    public function view(): View
    {
        return view('exports.vend-temp', [
            'vendTemps' => VendTempResource::collection($this->vendTemps),
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }
}
