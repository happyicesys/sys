<?php

namespace App\Jobs;

use App\Services\Products\WelcomeSketch\ProductWelcomeSketchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Checks one finished drawing against its photo (ProductWelcomeSketchService::check). One try: each
 * call is paid, and the service's pending → checking claim makes a redelivery a no-op.
 */
class CheckProductWelcomeSketch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 200;

    public function __construct(public readonly int $versionId)
    {
        $this->onQueue('low');
    }

    public function handle(ProductWelcomeSketchService $sketches): void
    {
        $sketches->check($this->versionId);
    }
}
