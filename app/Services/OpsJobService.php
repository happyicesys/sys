<?php

namespace App\Services;
use App\Jobs\CreateTransactionsCMS;
use App\Jobs\DeleteTransactionsCMS;
use App\Models\Customer;
use App\Models\OpsJobItem;
use Carbon\Carbon;

class OpsJobService
{
  public function createCMSEmptyInvoicesByOpsJobItem($customersArr, $date, $driver)
  {
    $data = [
        'date' => Carbon::parse($date)->format('Y-m-d'),
        'driver' => $driver->username,
        'created_by' => auth()->user()->username,
        'customers' => $customersArr,
    ];

    CreateTransactionsCMS::dispatch($data);

    return $data;
  }

  public function updateJobItemCMSTransactionID($data)
  {
    if($data) {
      foreach($data as $cmsCustomer) {
        $opsJobItem = OpsJobItem::where('id', $cmsCustomer['ops_job_item_id'])->first();

        if($opsJobItem) {
            $opsJobItem->cms_transaction_id = $cmsCustomer['transaction_id'];
            $opsJobItem->save();
        }
      }
    }
  }

  public function deleteJobItemCMSTransaction($opsJobItemID)
  {
    $opsJobItem = OpsJobItem::findOrFail($opsJobItemID);

    if($opsJobItem->cms_transaction_id) {
        DeleteTransactionsCMS::dispatch($opsJobItem->cms_transaction_id);
    }
  }
}