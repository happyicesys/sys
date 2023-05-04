<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Models\VendSnapshot;
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
        VendSnapshot::create([
            'customer_json' => $this->vend->latestVendBinding && $this->vend->latestVendBinding->customer ? $this->vend->latestVendBinding->customer  : null,
            'operator_id' => $this->vend->currentOperator()->exists() ? $this->vend->currentOperator->first()->id : null,
            'parameter_json' => $this->vend->parameter_json,
            'vend_channels_json' => $this->vend->vend_channels_json,
            'vend_code' => $this->vend->code,
            'vend_id' => $this->vend->id,
        ]);
    }
}