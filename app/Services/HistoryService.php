<?php

namespace App\Services;
use App\Models\Customer;
use App\Models\CustomerVendBinding;
use App\Models\Vend;
use Carbon\Carbon;

class HistoryService
{
  public function syncVendCustomerMovement(Vend $vend, Customer $customer, $isBinding = true)
  {
    CustomerVendBinding::create([
      'customer_id' => $customer->id,
      'is_binding' => $isBinding,
      'user_id' => auth()->user()->id,
      'vend_id' => $vend->id,
    ]);

    // $movementHistoryArr = $vend->customer_movement_history_json;
    // $movementHistoryArr[] = [
    //   'id' => $customer->id,
    //   'name' => $customer->name,
    //   'virtual_customer_prefix' => $customer->virtual_customer_prefix,
    //   'virtual_customer_code' => $customer->virtual_customer_code,
    //   'is_binding' => $isBinding,
    //   'created_at' => Carbon::now()->toDateTimeString(),
    //   'created_by' => auth()->user()->id,
    //   'created_by_name' => auth()->user()->name,
    // ];
    // $vend->update([
    //   'customer_movement_history_json' => $movementHistoryArr
    // ]);
  }
}