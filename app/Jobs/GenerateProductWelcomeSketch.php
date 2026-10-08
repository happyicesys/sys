<?php

namespace App\Jobs;

use App\Services\Products\WelcomeSketch\ProductWelcomeSketchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Redraws one product's welcome sketch (ProductWelcomeSketchService::generate). One try: each call
 * is a paid image request, and the service's pending → generating claim makes a redelivery
 * (redis retry_after is shorter than a generation) a no-op.
 */
class GenerateProductWelcomeSketch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 200;

    public function __construct(public readonly int $sketchId)
    {
        $this->onQueue('low');
    }

    public function handle(ProductWelcomeSketchService $sketches): void
    {
        $sketches->generate($this->sketchId);
    }
}
