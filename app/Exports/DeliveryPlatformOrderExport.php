<?php

namespace App\Exports;

use App\Models\DeliveryPlatformOrder;
use App\Http\Resources\DeliveryPlatformOrderResource;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class DeliveryPlatformOrderExport implements FromView, ShouldAutoSize, WithColumnFormatting
{
    protected $deliveryPlatformOrders;

    public function __construct($deliveryPlatformOrders)
    {
        $this->deliveryPlatformOrders = $deliveryPlatformOrders;
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'J' => '@',
        ];
    }

    public function view(): View
    {
        return view('exports.delivery-platform-orders', [
            'deliveryPlatformOrders' => DeliveryPlatformOrderResource::collection($this->deliveryPlatformOrders)
        ]);
    }
}
