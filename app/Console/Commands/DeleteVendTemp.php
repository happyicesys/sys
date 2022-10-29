<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Models\VendTemp;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeleteVendTemp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:vend-temp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete vending machine temperature';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        VendTemp::whereDate('created_at', '<=', Carbon::today()->subDays(30))->delete();

        $vends = Vend::with(['vendTemps' => function($query) {
            $query->whereDate('created_at', '<=', Carbon::today()->subDays(14));
        }])->has('vendTemps')->orderBy('code')->get();

        if($vends) {
            foreach($vends as $vend) {
                $firstIsKeep = $vend->vendTemp()->latest()->where('is_keep', true)->first();
                if(!$firstIsKeep) {
                    $firstIsKeep = $vend->vendTemp()->latest()->first();
                    $firstIsKeep->is_keep = true;
                    $firstIsKeep->save();
                }

            }
        }

        VendTemp::whereDate('created_at', '<=', Carbon::today()->subDays(14))->where('is_keep', false)->delete();
    }
}
