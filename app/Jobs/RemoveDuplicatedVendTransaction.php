<?php

namespace App\Jobs;

use App\Models\VendTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RemoveDuplicatedVendTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dateFrom;
    protected $dateTo;

    /**
     * Create a new job instance.
     */
    public function __construct($dateFrom, $dateTo)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::beginTransaction();

        // Find duplicate order_id entries
        $duplicates = VendTransaction::select('order_id', 'vend_id', DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->groupBy('order_id', 'vend_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            // Fetch all duplicate transactions
            $transactions = VendTransaction::where('order_id', $duplicate->order_id)
                ->where('vend_id', $duplicate->vend_id)
                ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
                ->orderBy('created_at', 'desc') // Keep the latest
                ->skip(1) // Delete all except the most recent
                ->pluck('id');

            // Delete the older duplicates
            VendTransaction::whereIn('id', $transactions)->delete();
        }

        DB::commit();
    }
}
