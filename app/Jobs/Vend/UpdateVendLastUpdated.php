<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateVendLastUpdated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $connectionType;
    protected $vend;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Vend $vend, $connectionType)
    {
        $this->connectionType = $connectionType;
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if($this->connectionType == 'mqtt') {
            $this->vend->update([
                'is_mqtt' => true,
                'is_mqtt_active' => true,
                'mqtt_last_updated_at' => Carbon::now(),
                'last_updated_at' => Carbon::now()
            ]);
        } else {
            $this->vend->update([
                'last_updated_at' => Carbon::now()
            ]);
        }
    }
}
