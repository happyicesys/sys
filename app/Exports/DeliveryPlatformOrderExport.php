<?php

namespace App\Exports;

use App\Models\DeliveryPlatformOrder;
use App\Http\Resources\DeliveryPlatformOrderResource;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class DeliveryPlatformOrderExport implements FromView, WithColumnFormatting
{
    protected $deliveryPlatformOrders;
    protected $deliveryPlatformOrderModel;

    public function __construct($deliveryPlatformOrders)
    {
        $this->deliveryPlatformOrders = $deliveryPlatformOrders;
        $this->deliveryPlatformOrderModel = new DeliveryPlatformOrder();
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'J' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    public function view(): View
    {
        return view('exports.delivery-platform-orders', [
            'deliveryPlatformOrders' => DeliveryPlatformOrderResource::collection($this->deliveryPlatformOrders),
            'deliveryPlatformOrderModel' => $this->deliveryPlatformOrderModel,
        ]);
    }
}
