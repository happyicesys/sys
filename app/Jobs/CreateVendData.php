<?php

namespace App\Jobs;

use App\Models\VendData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateVendData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $originalInput;
    protected $processedInput;
    protected $ipAddress;
    protected $connectionType;
    protected $type;
    protected $isKeep;

    /**
     * Create a new job instance.
     */
    public function __construct($originalInput, $processedInput, $ipAddress = null, $connectionType = null, $type = null, $isKeep = false)
    {
        $this->originalInput = $originalInput;
        $this->processedInput = $processedInput;
        $this->ipAddress = $ipAddress;
        $this->connectionType = $connectionType;
        $this->type = $type;
        $this->isKeep = $isKeep;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        VendData::create([
            'connection' => $this->connectionType,
            'ip_address' => $this->ipAddress,
            'processed' => $this->processedInput,
            'type' => $this->type ?? ($this->processedInput['Type'] ?? null),
            'value' => $this->originalInput,
            'vend_code' => isset($this->originalInput['m']) ? $this->originalInput['m'] : null,
            'is_keep' => $this->isKeep,
        ]);
    }
}
