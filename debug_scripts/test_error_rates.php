<?php
$vend = App\Models\Vend::where('code', '7006')->first();
$vendChannel = App\Models\VendChannel::where('vend_id', $vend->id)->where('code', '25')->first();

echo "Vend Channel ID for code 25: " . $vendChannel->id . "\n";

$transactions = App\Models\VendTransaction::where('vend_id', $vend->id)
    ->where('transaction_datetime', '>=', '2026-02-22 00:00:00')
    ->orderBy('created_at', 'desc')->take(5)->get();

foreach ($transactions as $t) {
    echo "ID: " . $t->id . ", Order: " . $t->order_id . " Channel ID: " . $t->vend_channel_id . " Channel Code: " . $t->vend_channel_code . " Error code: " . $t->error_code_normalized . " is_multiple: " . $t->is_multiple . "\n";
    $items = App\Models\VendTransactionItem::where('vend_transaction_id', $t->id)->get();
    foreach ($items as $item) {
        echo "  - Item Channel: " . $item->vend_channel_id . " Error: " . $item->vend_channel_error_code . "\n";
    }
}
