<?php

namespace App\Jobs;

use App\Models\ModemUnit;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateModemLastUpdated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $modemUnit;
    /**
     * Create a new job instance.
     */
    public function __construct(ModemUnit $modemUnit)
    {
        $this->modemUnit = $modemUnit;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->modemUnit->update([
            'is_online' => true,
            'last_updated_at' => Carbon::now(),
        ]);
    }
}
