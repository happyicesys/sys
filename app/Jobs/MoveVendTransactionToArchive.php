<?php

namespace App\Jobs;

use App\Models\VendTransactionArchive;
use App\Models\VendTransaction;
use App\Models\VendTransactionItemArchive;
use App\Models\VendTransactionItem;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MoveVendTransactionToArchive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dateBefore;
    /**
     * Create a new job instance.
     */
    public function __construct($dateBefore)
    {
        $this->dateBefore = $dateBefore;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $vendTransactions = VendTransaction::where('created_at', '<', $this->dateBefore)->get();

        if($vendTransactions) {
            foreach($vendTransactions as $vendTransaction) {
                DB::beginTransaction();
                $vendTransactionArchive = new VendTransactionArchive();
                $vendTransactionArchive->amount = $vendTransaction->amount;
                $vendTransactionArchive->created_at = $vendTransaction->created_at;
                $vendTransactionArchive->customer_id = $vendTransaction->customer_id;
                $vendTransactionArchive->customer_json = $vendTransaction->customer_json;
                $vendTransactionArchive->error_code_normalized = $vendTransaction->error_code_normalized;
                $vendTransactionArchive->gross_profit = $vendTransaction->gross_profit;
                $vendTransactionArchive->gross_profit_margin = $vendTransaction->gross_profit_margin;
                $vendTransactionArchive->gst_vat_rate = $vendTransaction->gst_vat_rate;
                $vendTransactionArchive->id = $vendTransaction->id;
                $vendTransactionArchive->is_multiple = $vendTransaction->is_multiple;
                $vendTransactionArchive->is_payment_received = $vendTransaction->is_payment_received;
                $vendTransactionArchive->is_refunded = $vendTransaction->is_refunded;
                $vendTransactionArchive->items_json = $vendTransaction->items_json;
                $vendTransactionArchive->location_type_json = $vendTransaction->location_type_json;
                $vendTransactionArchive->operator_id = $vendTransaction->operator_id;
                $vendTransactionArchive->operator_json = $vendTransaction->operator_json;
                $vendTransactionArchive->order_id = $vendTransaction->order_id;
                $vendTransactionArchive->payment_gateway_log_id = $vendTransaction->payment_gateway_log_id;
                $vendTransactionArchive->payment_method_id = $vendTransaction->payment_method_id;
                $vendTransactionArchive->product_id = $vendTransaction->product_id;
                $vendTransactionArchive->product_json = $vendTransaction->product_json;
                $vendTransactionArchive->revenue = $vendTransaction->revenue;
                $vendTransactionArchive->transaction_datetime = $vendTransaction->transaction_datetime;
                $vendTransactionArchive->unit_cost = $vendTransaction->unit_cost;
                $vendTransactionArchive->unit_cost_id = $vendTransaction->unit_cost_id;
                $vendTransactionArchive->save();

                if($vendTransaction->vendTransactionItems) {
                    foreach($vendTransaction->vendTransactionItems as $vendTransactionItem) {
                        $vendTransactionItemArchive = new VendTransactionItemArchive();
                        $vendTransactionItemArchive->created_at = $vendTransactionItem->created_at;
                        $vendTransactionItemArchive->id = $vendTransactionItem->id;
                        $vendTransactionItemArchive->is_refunded = $vendTransactionItem->is_refunded;
                        $vendTransactionItemArchive->product_id = $vendTransactionItem->product_id;
                        $vendTransactionItemArchive->product_json = $vendTransactionItem->product_json;
                        $vendTransactionItemArchive->unit_cost = $vendTransactionItem->unit_cost;
                        $vendTransactionItemArchive->unit_cost_id = $vendTransactionItem->unit_cost_id;
                        $vendTransactionItemArchive->updated_at = $vendTransactionItem->updated_at;
                        $vendTransactionItemArchive->vend_channel_id = $vendTransactionItem->vend_channel_id;
                        $vendTransactionItemArchive->vend_channel_code = $vendTransactionItem->vend_channel_code;
                        $vendTransactionItemArchive->vend_channel_error_id = $vendTransactionItem->vend_channel_error_id;
                        $vendTransactionItemArchive->vend_channel_error_json = $vendTransactionItem->vend_channel_error_json;
                        $vendTransactionItemArchive->vend_transaction_id = $vendTransactionItem->vend_transaction_id;
                        $vendTransactionItemArchive->save();
                    }

                    $vendTransaction->vendTransactionItems()->delete();
                }

                $vendTransaction->delete();
                DB::commit();
            }
        }
    }
}
