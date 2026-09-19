<?php

namespace App\Http\Resources;

use App\Models\OpsJob;
use Illuminate\Database\Eloquent\Model;

/**
 * The machine / site / job block every ops-job stop resource shows the same
 * way, so a service notice row and a stock count row cannot drift apart.
 */
final class StopResourceFields
{
    /** @param  Model&object{vend:?\App\Models\Vend, customer:?\App\Models\Customer}  $stop */
    public static function machine(Model $stop): array
    {
        $vend = $stop->relationLoaded('vend') ? $stop->vend : null;
        $customer = $stop->relationLoaded('customer') ? $stop->customer : null;
        $address = $customer && $customer->relationLoaded('deliveryAddress') ? $customer->deliveryAddress : null;

        return [
            'vend_id' => $stop->vend_id,
            'vend_code' => $vend?->codeLabel(),
            'machine_kind' => match (true) {
                $vend === null => null,
                $vend->isSmartChiller() => 'smart_chiller',
                $vend->isSmartFreezer() => 'smart_freezer',
                default => 'vending_machine',
            },
            'customer_id' => $stop->customer_id,
            'customer_name' => $customer?->name,
            'address' => $address ? (new AddressResource($address))->resolve() : null,
        ];
    }

    public static function opsJob(OpsJob $opsJob): array
    {
        return [
            'id' => $opsJob->id,
            'code' => $opsJob->code,
            'date' => $opsJob->date?->format('Y-m-d'),
            'date_label' => $opsJob->date?->format('ymd (D)'),
            'delivered_by_name' => $opsJob->relationLoaded('deliveredBy') ? $opsJob->deliveredBy?->name : null,
        ];
    }
}
