<?php

namespace App\Services;
use App\Jobs\DeleteTransactionsCMS;
use App\Models\Customer;
use App\Models\OpsJobItem;
use Carbon\Carbon;

class OpsJobService
{

  public function updateJobItemCMSTransactionID($data)
  {
    if($data) {
      foreach($data as $cmsCustomer) {
        $opsJobItem = OpsJobItem::where('id', $cmsCustomer['ops_job_item_id'])->first();

        if($opsJobItem) {
            $opsJobItem->cms_transaction_id = $cmsCustomer['transaction_id'];
            $opsJobItem->cms_transaction_at = Carbon::now();
            $opsJobItem->save();

            // $opsJobItem->customer()->update([
            //   'cms_invoice_history->next_transaction_id' => $cmsCustomer['transaction_id'],
            //   'cms_invoice_history->next_delivery_driver' => $opsJobItem->opsJob->deliveredBy->name,
            // ]);
        }
      }
    }
  }

  public function deleteJobItemCMSTransaction($opsJobItemID)
  {
    $opsJobItem = OpsJobItem::findOrFail($opsJobItemID);

    if($opsJobItem->cms_transaction_id) {
        DeleteTransactionsCMS::dispatch($opsJobItem->cms_transaction_id);

        $opsJobItem->customer()->update([
          'cms_invoice_history->next_transaction_id' => null,
          'cms_invoice_history->next_delivery_driver' => null,
          'cms_invoice_history->next_delivery_date' => null,
        ]);
    }
  }
}