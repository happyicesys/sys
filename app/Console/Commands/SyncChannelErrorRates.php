<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;

class SyncChannelErrorRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vend:sync-channel-error-rates {vend_code?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually re-sync historical 1d, 2d and 7d multi-transaction error rates for vend channels';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $vendCode = $this->argument('vend_code');

        $query = Vend::query()
            ->where('is_active', true)
            ->where('is_testing', false);

        if ($vendCode) {
            $query->where('code', $vendCode);
        }

        $vends = $query->get();
        $this->info("Found " . count($vends) . " active vends to process...");

        $bar = $this->output->createProgressBar(count($vends));

        $sixDaysAgo = \Carbon\Carbon::today()->subDays(6)->startOfDay()->toDateTimeString();
        $oneDayAgo = \Carbon\Carbon::today()->subDays(1)->startOfDay()->toDateTimeString();
        $todayStart = \Carbon\Carbon::today()->startOfDay()->toDateTimeString();

        foreach ($vends as $vend) {
            $channels = VendChannel::where('vend_id', $vend->id)->get();

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
                        COUNT(CASE WHEN vend_channel_error_id IS NOT NULL AND vend_channel_error_id NOT IN (1) THEN 1 END) as seven_days_error_count,
                        COUNT(CASE WHEN transaction_datetime >= ? THEN id ELSE NULL END) as two_days_total_count,
                        COUNT(CASE WHEN transaction_datetime >= ? AND vend_channel_error_id IS NOT NULL AND vend_channel_error_id NOT IN (1) THEN 1 END) as two_days_error_count,
                        COUNT(CASE WHEN transaction_datetime >= ? THEN id ELSE NULL END) as one_day_total_count,
                        COUNT(CASE WHEN transaction_datetime >= ? AND vend_channel_error_id IS NOT NULL AND vend_channel_error_id NOT IN (1) THEN 1 END) as one_day_error_count
                    ', [$oneDayAgo, $oneDayAgo, $todayStart, $todayStart])
                    ->first();

                $multiData = \App\Models\VendTransactionItem::query()
                    ->join('vend_transactions', 'vend_transaction_items.vend_transaction_id', '=', 'vend_transactions.id')
                    ->where('vend_transaction_items.vend_channel_id', $vendChannelID)
                    ->where('vend_transactions.is_multiple', true)
                    ->where('vend_transactions.transaction_datetime', '>=', $sixDaysAgo)
                    ->selectRaw('
                        COUNT(vend_transaction_items.id) as seven_days_total_count,
                        COUNT(CASE WHEN vend_transaction_items.vend_channel_error_code IS NOT NULL AND vend_transaction_items.vend_channel_error_code != "0" THEN 1 END) as seven_days_error_count,
                        COUNT(CASE WHEN vend_transactions.transaction_datetime >= ? THEN vend_transaction_items.id ELSE NULL END) as two_days_total_count,
                        COUNT(CASE WHEN vend_transactions.transaction_datetime >= ? AND vend_transaction_items.vend_channel_error_code IS NOT NULL AND vend_transaction_items.vend_channel_error_code != "0" THEN 1 END) as two_days_error_count,
                        COUNT(CASE WHEN vend_transactions.transaction_datetime >= ? THEN vend_transaction_items.id ELSE NULL END) as one_day_total_count,
                        COUNT(CASE WHEN vend_transactions.transaction_datetime >= ? AND vend_transaction_items.vend_channel_error_code IS NOT NULL AND vend_transaction_items.vend_channel_error_code != "0" THEN 1 END) as one_day_error_count
                    ', [$oneDayAgo, $oneDayAgo, $todayStart, $todayStart])
                    ->first();

                $sevenDaysTotal = ($singleData->seven_days_total_count ?? 0) + ($multiData->seven_days_total_count ?? 0);
                $sevenDaysError = ($singleData->seven_days_error_count ?? 0) + ($multiData->seven_days_error_count ?? 0);
                $twoDaysTotal = ($singleData->two_days_total_count ?? 0) + ($multiData->two_days_total_count ?? 0);
                $twoDaysError = ($singleData->two_days_error_count ?? 0) + ($multiData->two_days_error_count ?? 0);
                $oneDayTotal = ($singleData->one_day_total_count ?? 0) + ($multiData->one_day_total_count ?? 0);
                $oneDayError = ($singleData->one_day_error_count ?? 0) + ($multiData->one_day_error_count ?? 0);

                $newRateJson = [
                    'seven_days_total_count' => $sevenDaysTotal,
                    'seven_days_error_count' => $sevenDaysError,
                    'seven_days_error_rate' => $sevenDaysTotal > 0 ? round(($sevenDaysError / $sevenDaysTotal) * 100, 2) : 0,
                    'two_days_total_count' => $twoDaysTotal,
                    'two_days_error_count' => $twoDaysError,
                    'two_days_error_rate' => $twoDaysTotal > 0 ? round(($twoDaysError / $twoDaysTotal) * 100, 2) : 0,
                    'one_day_total_count' => $oneDayTotal,
                    'one_day_error_count' => $oneDayError,
                    'one_day_error_rate' => $oneDayTotal > 0 ? round(($oneDayError / $oneDayTotal) * 100, 2) : 0,
                ];

                $channel->error_rate_json = $newRateJson;
                $channel->save();
            }
            \App\Jobs\Vend\SaveVendChannelsJson::dispatch($vend->id)->onQueue('default');
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Channel Error Rates Synced Successfully!");
        return Command::SUCCESS;
    }
}
