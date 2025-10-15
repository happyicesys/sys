<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendAlertParameterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $operator = $this->operator;
        $customer = $this->customer;
        $machineName = $this->name ?? $customer?->name ?? null;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'display_name' => $machineName,
            'operator' => $operator ? [
                'id' => $operator->id,
                'name' => $operator->name,
                'code' => $operator->code,
                'full_name' => trim(implode(' - ', array_filter([$operator->code, $operator->name]))),
            ] : null,
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'code' => $customer->code,
            ] : null,
            'last_vend_transaction_at' => optional($this->last_vend_transaction_at)->toIso8601String(),
            'last_updated_at' => optional($this->last_updated_at)->toIso8601String(),
            'offline_alert_minutes' => $this->offlineAlertMinutes(),
            'power_restored_alert_minutes' => $this->powerRestoredAlertMinutes(),
            'no_sales_alert_hours' => $this->noSalesAlertHours(),
            'overrides' => [
                'offline_after_minutes' => $this->alertSetting?->offline_after_minutes,
                'power_restored_after_minutes' => $this->alertSetting?->power_restored_after_minutes,
                'no_sales_after_hours' => $this->alertSetting?->no_sales_after_hours,
            ],
            'defaults' => [
                'offline_after_minutes' => \App\Models\Vend::DEFAULT_OFFLINE_ALERT_MINUTES,
                'power_restored_after_minutes' => \App\Models\Vend::DEFAULT_POWER_RESTORED_ALERT_MINUTES,
                'no_sales_after_hours' => \App\Models\Vend::DEFAULT_NO_SALES_ALERT_HOURS,
            ],
        ];
    }
}
