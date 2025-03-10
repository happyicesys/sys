<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Services\VendTransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateVendTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $input;
    protected $vend;
    protected $isCurrentTime;
    protected $vendTransactionService;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($input, Vend $vend, $isCurrentTime = true)
    {
        $this->input = $input;
        $this->vend = $vend;
        $this->isCurrentTime = $isCurrentTime;
        $this->vendTransactionService = new VendTransactionService();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->vendTransactionService->create($this->vend, $this->input, $this->isCurrentTime);
    }



}
