<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Models\VendSnapshot;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaveVendStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vend;
    /**
     * Create a new job instance.
     */
    public function __construct(Vend $vend)
    {
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if($this->vend && $this->vend->customer) {
            VendSnapshot::create([
                'customer_id' => $this->vend->customer->id,
                'customer_json' => $this->vend->customer,
                'operator_id' => $this->vend->customer->operator()->exists() ? $this->vend->customer->operator->id : 1,
                'parameter_json' => $this->vend->parameter_json,
                'vend_channels_json' => $this->vend->vend_channels_json,
                'vend_code' => $this->vend->code,
                'vend_id' => $this->vend->id,
            ]);
        }


        // VendSnapshot::whereDate('created_at', '<', Carbon::today()->subYears(2))->delete();
    }
}
