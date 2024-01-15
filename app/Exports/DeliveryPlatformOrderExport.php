<?php

namespace App\Exports;

use App\Models\DeliveryPlatformOrder;
use App\Http\Resources\DeliveryPlatformOrderResource;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class DeliveryPlatformOrderExport implements FromView
{
    protected $deliveryPlatformOrders;

    public function __construct($deliveryPlatformOrders)
    {
        $this->deliveryPlatformOrders = $deliveryPlatformOrders;
    }

    public function view(): View
    {
        return view('exports.delivery-platform-orders', [
            'deliveryPlatformOrders' => $this->deliveryPlatformOrders
        ]);
    }
}
