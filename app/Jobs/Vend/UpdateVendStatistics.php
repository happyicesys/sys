<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Models\VendChannelRecord;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateVendStatistics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $input;
    protected $vend;
    /**
     * Create a new job instance.
     */
    public function __construct($input, Vend $vend)
    {
        $this->input = $input;
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->vend->update([
            'statistics1_json' => $this->input,
        ]);

        if(isset($this->input['ACT'])) {
            $vendChannelRecord = null;

            switch($this->input['ACT']) {
                case 'B':
                    $vendChannelRecord = VendChannelRecord::query()
                        ->where('vend_id', $this->vend->id)
                        ->where('before_label', 'B')
                        ->whereNull('before_statis_json')
                        ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, before_data_created_at, ?))', [Carbon::now()])
                        ->first();

                    if($vendChannelRecord) {
                        $vendChannelRecord->update([
                            'before_statis_json' => $this->input,
                        ]);
                    }
                    break;
                case 'A':
                    $vendChannelRecord = VendChannelRecord::query()
                        ->where('vend_id', $this->vend->id)
                        ->where('after_label', 'A')
                        ->whereNull('after_statis_json')
                        ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, after_data_created_at, ?))', [Carbon::now()])
                        ->first();

                    if($vendChannelRecord) {
                        $vendChannelRecord->update([
                            'after_statis_json' => $this->input,
                        ]);
                    }
                    break;
            }
        }
    }
}
