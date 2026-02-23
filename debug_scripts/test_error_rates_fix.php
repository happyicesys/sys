<?php
$vendChannelID = 1276;
$sixDaysAgo = \Carbon\Carbon::today()->subDays(6)->startOfDay()->toDateTimeString();
$twoDaysAgo = \Carbon\Carbon::today()->subDays(2)->startOfDay()->toDateTimeString();

// Count from single vend transactions
$singleData = \App\Models\VendTransaction::query()
    ->where('vend_channel_id', $vendChannelID)
    ->where('is_multiple', false)
    ->where('transaction_datetime', '>=', $sixDaysAgo)
    ->selectRaw('
        COUNT(id) as seven_days_total_count,
        COUNT(CASE WHEN error_code_normalized IS NOT NULL AND error_code_normalized != 0 THEN 1 END) as seven_days_error_count,
        COUNT(CASE WHEN transaction_datetime >= ? THEN id ELSE NULL END) as three_days_total_count,
        COUNT(CASE WHEN transaction_datetime >= ? AND error_code_normalized IS NOT NULL AND error_code_normalized != 0 THEN 1 END) as three_days_error_count
    ', [$twoDaysAgo, $twoDaysAgo])
    ->first();

// Count from multi vend transaction items
$multiData = \App\Models\VendTransactionItem::query()
    ->join('vend_transactions', 'vend_transaction_items.vend_transaction_id', '=', 'vend_transactions.id')
    ->where('vend_transaction_items.vend_channel_id', $vendChannelID)
    ->where('vend_transactions.is_multiple', true)
    ->where('vend_transactions.transaction_datetime', '>=', $sixDaysAgo)
    ->selectRaw('
        COUNT(vend_transaction_items.id) as seven_days_total_count,
        COUNT(CASE WHEN vend_transaction_items.vend_channel_error_code IS NOT NULL AND vend_transaction_items.vend_channel_error_code != "0" THEN 1 END) as seven_days_error_count,
        COUNT(CASE WHEN vend_transactions.transaction_datetime >= ? THEN vend_transaction_items.id ELSE NULL END) as three_days_total_count,
        COUNT(CASE WHEN vend_transactions.transaction_datetime >= ? AND vend_transaction_items.vend_channel_error_code IS NOT NULL AND vend_transaction_items.vend_channel_error_code != "0" THEN 1 END) as three_days_error_count
    ', [$twoDaysAgo, $twoDaysAgo])
    ->first();

$total_seven_days = ($singleData->seven_days_total_count ?? 0) + ($multiData->seven_days_total_count ?? 0);
$err_seven_days = ($singleData->seven_days_error_count ?? 0) + ($multiData->seven_days_error_count ?? 0);
$total_three_days = ($singleData->three_days_total_count ?? 0) + ($multiData->three_days_total_count ?? 0);
$err_three_days = ($singleData->three_days_error_count ?? 0) + ($multiData->three_days_error_count ?? 0);

echo "Single Data: 7d_to: {$singleData->seven_days_total_count} 7d_er: {$singleData->seven_days_error_count} 3d_to: {$singleData->three_days_total_count} 3d_er: {$singleData->three_days_error_count}\n";
echo "Multi Data:  7d_to: {$multiData->seven_days_total_count} 7d_er: {$multiData->seven_days_error_count} 3d_to: {$multiData->three_days_total_count} 3d_er: {$multiData->three_days_error_count}\n";
echo "Totals: 7d_to: $total_seven_days 7d_er: $err_seven_days 3d_to: $total_three_days 3d_er: $err_three_days\n";
