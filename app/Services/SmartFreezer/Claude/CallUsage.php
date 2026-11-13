<?php

namespace App\Services\SmartFreezer\Claude;

/**
 * What one Claude call used and cost. The cost is integer millionths of a US dollar, from the model the API
 * says answered (a fallback may answer instead of the one asked) and config
 * `smart_freezer.claude_recognition.prices`; null when that model has no price on file.
 */
final class CallUsage
{
    public function __construct(
        public readonly ?string $model,
        public readonly ?string $messageId,
        public readonly ?string $stopReason,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $cacheWriteTokens,
        public readonly int $cacheReadTokens,
    ) {}

    /** Two calls' usage as one (the session's bill); the first call's model and message id are kept. */
    public function plus(self $other): self
    {
        return new self(
            model: $this->model ?? $other->model,
            messageId: $this->messageId ?? $other->messageId,
            stopReason: $other->stopReason ?? $this->stopReason,
            inputTokens: $this->inputTokens + $other->inputTokens,
            outputTokens: $this->outputTokens + $other->outputTokens,
            cacheWriteTokens: $this->cacheWriteTokens + $other->cacheWriteTokens,
            cacheReadTokens: $this->cacheReadTokens + $other->cacheReadTokens,
        );
    }

    public function costUsdMicros(): ?int
    {
        $price = $this->model !== null ? (config('smart_freezer.claude_recognition.prices')[$this->model] ?? null) : null;
        if (! is_array($price)) {
            return null;
        }

        // Dollars per million tokens × tokens = micro-dollars; rounded once, up, so a cost is never under-stated.
        $micros = $this->inputTokens * (float) $price['input']
            + $this->outputTokens * (float) $price['output']
            + $this->cacheWriteTokens * (float) ($price['cache_write'] ?? $price['input'])
            + $this->cacheReadTokens * (float) ($price['cache_read'] ?? $price['input']);

        return (int) ceil(round($micros, 6));
    }

    /** @return array<string, mixed> the row's usage columns */
    public function columns(): array
    {
        return [
            'model' => $this->model,
            'message_id' => $this->messageId,
            'stop_reason' => $this->stopReason,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cache_write_tokens' => $this->cacheWriteTokens,
            'cache_read_tokens' => $this->cacheReadTokens,
            'cost_usd_micros' => $this->costUsdMicros(),
        ];
    }
}
