<?php

namespace App\Console\Commands;

use App\Jobs\Vend\SaveVendStatus;
use App\Models\Vend;
use App\Models\VendSnapshot;
use Illuminate\Console\Command;

class ExportVendsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:vends-status {vendCodes?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export all vends status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $vendCodesStr = $this->argument('vendCodes');
        if($vendCodesStr) {
            if(strpos($vendCodesStr, ',') !== false) {
                $vendCodesArr = explode(',', $vendCodesStr);
            }else {
                $vendCodesArr = [$vendCodesStr];
            }
            $vends = Vend::whereIn('vend_code', $vendCodesArr)->get();
        }else {
            $vends = Vend::all();
        }

        if($vends) {
            foreach($vends as $vend) {
                SaveVendStatus::dispatch($vend);
            }
        }

    }
}
