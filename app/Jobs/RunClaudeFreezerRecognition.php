<?php

namespace App\Jobs;

use App\Models\SmartFreezerClaudeRecognition;
use App\Services\SmartFreezer\Claude\ClaudeShadowRecognitionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Claude's count of one freezer door session (ClaudeShadowRecognitionService::run), off the push's path.
 * One try: the call is billed, the row's pending → running claim stops a second one, and a failure stays on
 * the row (`smart-freezer:claude-recognition --id=<id> --retry` sends it again).
 */
class RunClaudeFreezerRecognition implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /** Video download + frames + a Claude call of up to `timeout_seconds` (300). */
    public int $timeout = 600;

    public function __construct(public readonly int $rowId)
    {
        $this->onQueue('low');
    }

    public function handle(ClaudeShadowRecognitionService $claude): void
    {
        $row = SmartFreezerClaudeRecognition::find($this->rowId);
        if ($row !== null && $row->status === SmartFreezerClaudeRecognition::STATUS_PENDING) {
            $claude->run($row);
        }
    }
}
