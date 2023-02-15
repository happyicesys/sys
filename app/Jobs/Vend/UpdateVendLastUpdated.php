<?php

namespace App\Jobs\Vend;

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

    protected $vend;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Vend $vend)
    {
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->vend->update([
            'last_updated_at' => Carbon::now()
        ]);
    }
}
