<?php
$vend = App\Models\Vend::where('code', '7006')->first();
$transactions = App\Models\VendTransaction::where('vend_id', $vend->id)
    ->where('transaction_datetime', '>=', '2026-02-22 00:00:00')
    ->orderBy('created_at', 'desc')->take(5)->get();

foreach ($transactions as $t) {
    echo "ID: " . $t->id . ", Order: " . $t->order_id . " is_multiple: " . $t->is_multiple . "\n";
    if ($t->is_multiple) {
        $items = App\Models\VendTransactionItem::where('vend_transaction_id', $t->id)->get();
        foreach ($items as $item) {
            echo "  - Item Channel Code: " . $item->vend_channel_code . " Error code: " . $item->vend_channel_error_code . " ErrID: " . $item->vend_channel_error_id . "\n";
        }
    }
}
