<?php
$vend = App\Models\Vend::where('code', '7017')->first();
$channels = App\Models\VendChannel::where('vend_id', $vend->id)->get();

$sixDaysAgo = \Carbon\Carbon::today()->subDays(6)->startOfDay()->toDateTimeString();
$twoDaysAgo = \Carbon\Carbon::today()->subDays(2)->startOfDay()->toDateTimeString();

echo "Calculating for Vend: {$vend->code} (ID: {$vend->id})\n";
$sum7d = 0;

foreach ($channels as $channel) {
    if (!$channel->is_active)
        continue;

    $vendChannelID = $channel->id;

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

    $sevenDaysTotal = ($singleData->seven_days_total_count ?? 0) + ($multiData->seven_days_total_count ?? 0);
    $sevenDaysError = ($singleData->seven_days_error_count ?? 0) + ($multiData->seven_days_error_count ?? 0);
    $threeDaysTotal = ($singleData->three_days_total_count ?? 0) + ($multiData->three_days_total_count ?? 0);
    $threeDaysError = ($singleData->three_days_error_count ?? 0) + ($multiData->three_days_error_count ?? 0);

    $sum7d += $sevenDaysTotal;

    $newRateJson = [
        'seven_days_total_count' => $sevenDaysTotal,
        'seven_days_error_count' => $sevenDaysError,
        'seven_days_error_rate' => $sevenDaysTotal > 0 ? round(($sevenDaysError / $sevenDaysTotal) * 100, 2) : 0,
        'three_days_total_count' => $threeDaysTotal,
        'three_days_error_count' => $threeDaysError,
        'three_days_error_rate' => $threeDaysTotal > 0 ? round(($threeDaysError / $threeDaysTotal) * 100, 2) : 0,
    ];

    $channel->error_rate_json = $newRateJson;
    $channel->save();
}

echo "Total purchased qty calculated (last 7 days): $sum7d\n";
