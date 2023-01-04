<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Models\VendTemp;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AddNullValueVendTime extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add:null-vend-temp-value';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert null value to offline vendtemp';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $vends = Vend::all();
        $types = [VendTemp::TYPE_CHAMBER, VendTemp::TYPE_EVAPORATOR];

        if($vends) {
            foreach($vends as $vend) {
                foreach($types as $type) {
                    $vendTemps = $vend
                    ->vendTemps()
                    ->where('type', $type)
                    ->whereDate('created_at', '>=', Carbon::today()->toDateString())
                    ->whereDate('created_at', '<=', Carbon::today()->toDateString())
                    ->get();

                    if($vendTemps) {
                        for($i=0; $i<count($vendTemps); $i++) {
                            if($i > 0) {
                                $past = Carbon::parse($vendTemps[$i - 1]['created_at']);
                                $current = Carbon::parse($vendTemps[$i]['created_at']);
                                $temPast = null;
                                $temCurrent = null;
                                if($past->diffInMinutes($current) >= 10) {
                                    $temPast = $past;
                                    $temCurrent = $temPast->copy()->addMinutes(10);
                                    while($temCurrent->diffInMinutes($current) >= 10) {
                                        $vend->vendTemps()->create([
                                            'value' => 'NaN',
                                            'temp_time' => $temCurrent,
                                            'type' => $type,
                                        ]);
                                        $temPast = $temCurrent;
                                        $temCurrent = $temCurrent->copy()->addMinutes(10);
                                    }
                                }
                                if($i == count($vendTemps) - 1 and $current->diffInMinutes(Carbon::now()) >= 10) {
                                    $temCurrent = $current;
                                    while($temCurrent->diffInMinutes(Carbon::now()) >= 10) {
                                        $vend->vendTemps()->create([
                                            'value' => 'NaN',
                                            'temp_time' => $temCurrent,
                                            'type' => $type,
                                        ]);
                                        $temCurrent = $temCurrent->copy()->addMinutes(10);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
