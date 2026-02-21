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

    public $tries = 0;
    public $timeout = 2;

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
        $now = Carbon::now();
        if (!$this->modemUnit->last_updated_at || $this->modemUnit->last_updated_at->diffInSeconds($now) >= 30 || !$this->modemUnit->is_online) {
            $this->modemUnit->update([
                'is_online' => true,
                'last_updated_at' => clone $now,
            ]);
        }
    }
}
