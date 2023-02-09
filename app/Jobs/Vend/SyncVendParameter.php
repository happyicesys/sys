<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Models\VendFan;
use App\Models\VendTemp;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncVendParameter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $input;
    protected $vend;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($input, Vend $vend)
    {
        $this->input = $input;
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $input = $this->input;
        $vend = $this->vend;

        $this->createVendFan($input, $vend);
        $this->createVendTemp($input, $vend);
        $this->saveParameter($input, $vend);
    }

    private function createVendFan($input, Vend $vend)
    {
        if(isset($input['fan']) and $input['fan']) {
            $vend->vendFans()->create([
                'value' => $input['fan'],
                'type' => VendFan::TYPE_MAIN,
            ]);
        }
    }

    private function createVendTemp($input, Vend $vend)
    {
        // more than 3 minutes only update same machine temp
        // if(!$vend->temp_updated_at or $vend->temp_updated_at->addMinutes(2)->isPast()) {
            if($temp = $input['TEMP']) {
                if($temp == VendTemp::TEMPERATURE_ERROR) {
                    $vend->is_temp_error = true;
                }else {
                    $createdTemp = $vend->vendTemps()->create([
                        'value' => $temp,
                        'type' => VendTemp::TYPE_CHAMBER,
                    ]);

                    if(isset($input['t2'])) {
                        $tempEvaporator = $input['t2'];
                        $vend->vendTemps()->create([
                            'value' => $tempEvaporator,
                            'type' => VendTemp::TYPE_EVAPORATOR,
                        ]);
                    }

                    if(isset($input['t3'])) {
                        $temp3 = $input['t3'];
                        $vend->vendTemps()->create([
                            'value' => $temp3,
                            'type' => VendTemp::TYPE_THREE,
                        ]);
                    }

                    if(isset($input['t4'])) {
                        $temp4 = $input['t4'];
                        $vend->vendTemps()->create([
                            'value' => $temp4,
                            'type' => VendTemp::TYPE_FOUR,
                        ]);
                    }

                    $vend->temp = $temp;
                    $vend->is_temp_error = false;
                }
            }
            $vend->temp_updated_at = Carbon::now();
            $vend->save();
        // }
    }

    private function saveParameter($input, Vend $vend)
    {
        $vend->parameter_json = $input;
        $vend->save();
    }
}
