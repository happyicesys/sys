<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Jobs\SyncBackDateVendTransaction;
use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Jobs\StoreVendsRecord;
use App\Services\RunningNumberService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CreateFVMBackDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:fvm-back-date';
    protected $runningNumberService;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create back date FVM transaction';


    public function __construct()
    {
        parent::__construct();
        $this->runningNumberService = new RunningNumberService();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $data = [
            ['vend' => 1804, 'cash' => 149.00, 'cashless' => 117.54],
            ['vend' => 1803, 'cash' => 722.00, 'cashless' => 389.30],
            ['vend' => 1802, 'cash' => 823.10, 'cashless' => 266.34],
            ['vend' => 1801, 'cash' => 273.00, 'cashless' => 151.53],
            ['vend' => 1805, 'cash' => 298.50, 'cashless' => 195.94],
            ['vend' => 1806, 'cash' => 195.00, 'cashless' => 326.20],
            ['vend' => 1807, 'cash' => 32.00, 'cashless' => 57.44],
            ['vend' => 1808, 'cash' => 76.00, 'cashless' => 156.22],
            ['vend' => 1809, 'cash' => 151.20, 'cashless' => 0.00],
            ['vend' => 1810, 'cash' => 0.00, 'cashless' => 243.89],
            ['vend' => 1811, 'cash' => 0.00, 'cashless' => 224.72],
        ];


        $dayCountInMonth = Carbon::now()->subMonth()->daysInMonth;

        if($data) {
            foreach ($data as $vend) {
                $dailyCash = 0;
                $dailyCashless = 0;

                if($cash = $vend['cash'] * 100) {
                    // Calculate basic division and remainder for cash
                    $dailyCash = intval($cash / $dayCountInMonth);
                    $cashRemainder = $cash % $dayCountInMonth; // Remainder
                }

                if($cashless = $vend['cashless'] * 100) {
                    // Calculate basic division and remainder for cashless
                    $dailyCashless = intval($cashless / $dayCountInMonth);
                    $cashlessRemainder = $cashless % $dayCountInMonth; // Remainder
                }

                for($i = 0; $i < $dayCountInMonth; $i++) {
                    if ($dailyCash > 0 || $dailyCashless > 0) {
                        $vendModel = Vend::where('code', $vend['vend'])->firstOrFail(); // Fetch vend model
                    } else {
                        continue;
                    }

                    // Adjust daily cash for remainder distribution
                    $adjustedCash = $dailyCash;
                    if ($i < $cashRemainder) {
                        $adjustedCash++; // Add 1 cent to distribute the remainder
                    }

                    // Adjust daily cashless for remainder distribution
                    $adjustedCashless = $dailyCashless;
                    if ($i < $cashlessRemainder) {
                        $adjustedCashless++; // Add 1 cent to distribute the remainder
                    }

                    if ($adjustedCash > 0 and $vend['cash'] > 0) {
                        $date = Carbon::now()->subMonth()->startOfMonth()->addDays($i)->format('Y-m-d') . ' 00:00:00';
                        $inputDailyCash = [
                            'date' => $date,
                            'amount' => $adjustedCash,
                            'orderID' => $this->runningNumberService->getVendOrderIDBasedOnDate($vendModel, $date),
                            'paymentMethodID' => 1,
                        ];

                        SyncBackDateVendTransaction::dispatch($vendModel, $inputDailyCash);
                    }

                    if ($adjustedCashless > 0 and $vend['cashless'] > 0) {
                        $date = Carbon::now()->subMonth()->startOfMonth()->addDays($i)->format('Y-m-d') . ' 00:01:00';
                        $inputDailyCashless = [
                            'date' => $date,
                            'amount' => $adjustedCashless,
                            'orderID' => $this->runningNumberService->getVendOrderIDBasedOnDate($vendModel, $date),
                            'paymentMethodID' => 2,
                        ];

                        SyncBackDateVendTransaction::dispatch($vendModel, $inputDailyCashless);
                    }
                }

                // SyncVendTransactionTotalsJson::dispatch($vend)->onQueue('default');
            }

            StoreVendsRecord::dispatch(Carbon::now()->subMonth()->startOfMonth()->toDateString(), Carbon::now()->subMonth()->endOfMonth()->toDateString(), true);


            foreach($data as $vend) {
                $vend = Vend::where('code', $vend['vend'])->firstOrFail();
                SyncVendTransactionTotalsJson::dispatch($vend)->onQueue('default');
            }
        }
    }
}
