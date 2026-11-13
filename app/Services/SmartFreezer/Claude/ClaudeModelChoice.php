<?php

namespace App\Services\SmartFreezer\Claude;

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Which Claude model counts the freezer door sessions (Brian, 2026-11-13: "able to select which model we are going to
 * use"). Picked on AI Recognition (permission `update ai-recognition`) and kept on the settings row; with nothing picked,
 * or a pick no longer offered, it is config `smart_freezer.claude_recognition.model`. The one reader is
 * ClaudeFreezerCounter::model().
 */
final class ClaudeModelChoice
{
    /** The token use a session is estimated at for the picker's price hint (12 frames a camera, two cameras). */
    private const ESTIMATE_INPUT_TOKENS = 25_000;

    private const ESTIMATE_OUTPUT_TOKENS = 2_000;

    /** @return array<string, string> model id => label */
    public static function offered(): array
    {
        $prices = (array) config('smart_freezer.claude_recognition.prices');

        return array_filter((array) config('smart_freezer.claude_recognition.models'), fn ($label, $id) => isset($prices[$id]), ARRAY_FILTER_USE_BOTH);
    }

    public static function current(): string
    {
        $picked = Setting::singleton()?->claude_freezer_ai_model;

        return is_string($picked) && isset(self::offered()[$picked])
            ? $picked
            : (string) config('smart_freezer.claude_recognition.model', 'claude-opus-5-5');
    }

    public static function pick(string $model, User $by): void
    {
        if (! isset(self::offered()[$model])) {
            throw new InvalidArgumentException("Model {$model} is not offered.");
        }
        $setting = Setting::query()->first() ?? new Setting;
        $setting->fill([
            'claude_freezer_ai_model' => $model,
            'claude_freezer_ai_model_set_by' => $by->id,
            'claude_freezer_ai_model_set_at' => Carbon::now(),
        ])->save();
    }

    /** Roughly what one session costs on $model, in Singapore dollars, from the price table. */
    public static function estimateSgd(string $model): ?float
    {
        $usage = new CallUsage($model, null, null, self::ESTIMATE_INPUT_TOKENS, self::ESTIMATE_OUTPUT_TOKENS, 0, 0);
        $micros = $usage->costUsdMicros();

        return $micros === null ? null : round($micros / 1_000_000 * (float) config('smart_freezer.claude_recognition.usd_to_sgd'), 3);
    }

    /** @return array<string, mixed> what the picker shows */
    public static function forPage(?User $viewer): array
    {
        $setting = Setting::singleton();
        $by = $setting?->claude_freezer_ai_model_set_by ? User::find($setting->claude_freezer_ai_model_set_by) : null;

        return [
            'current' => self::current(),
            'options' => collect(self::offered())->map(fn (string $label, string $id) => [
                'id' => $id, 'label' => $label, 'estimate_sgd' => self::estimateSgd($id),
            ])->values()->all(),
            'set_by' => $by?->name,
            'set_at' => $setting?->claude_freezer_ai_model_set_at?->format('Y-m-d H:i'),
            // CLAUDE_FREEZER_AI=true: AI Recognition shows the Claude Verdict column (Brian, 2026-11-13).
            'enabled' => (bool) config('smart_freezer.claude_recognition.enabled'),
            'on' => (bool) config('smart_freezer.claude_recognition.enabled') && trim((string) config('services.anthropic.api_key')) !== '',
            'can_change' => (bool) $viewer?->can('update ai-recognition'),
        ];
    }
}
