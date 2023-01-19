<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Models\VendTemp;
use Illuminate\Console\Command;

class SearchTempProblem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:temp-prob';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug temp prob';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $vends = Vend::all();
        $types = [
            VendTemp::TYPE_CHAMBER,
            VendTemp::TYPE_EVAPORATOR,
            VendTemp::TYPE_THREE,
            VendTemp::TYPE_FOUR,
        ];

        foreach($vends as $vend) {
            if($vend->vendTemps()->exists()) {
                $temps = [];
                foreach($types as $type) {
                    $temps[$type] = $vend
                            ->vendTemps()
                            ->where('type', $type)
                            ->get();
                }

            }
        }
    }
}
