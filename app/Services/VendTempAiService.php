<?php

namespace App\Services;

use App\Models\Vend;
use App\Models\VendTemp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VendTempAiService
{
    private const TEMP_DIVISOR = 10;

    public function isEnabled(): bool
    {
        $config = config('services.openai');

        return !empty(data_get($config, 'api_key'))
            && (bool) data_get($config, 'vend_temp.enabled', false);
    }

    /**
     * Analyse recent temperature telemetry with OpenAI.
     */
    public function analyze(Vend $vend, array $snapshot = [], ?VendTemp $latestTemp = null): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $config = config('services.openai.vend_temp', []);

        $temps = $this->recentTemps($vend, (int) data_get($config, 'window_minutes', 45), (int) data_get($config, 'max_samples', 30));
        if ($temps->isEmpty()) {
            return null;
        }

        $stats = $this->buildStats($temps);
        $payload = [
            'vend' => [
                'id' => $vend->id,
                'code' => $vend->code,
                'name' => $vend->name ?? null,
            ],
            'snapshot' => $this->formatSnapshot($snapshot, $latestTemp),
            'stats' => $stats,
            'samples' => $this->formatSamples($temps),
            'generated_at' => now()->toIso8601String(),
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.openai.api_key'),
        ])->post(rtrim(config('services.openai.base_uri'), '/').'/chat/completions', [
            'model' => data_get($config, 'model', 'gpt-4o-mini'),
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert monitoring freezer vending machines. Respond with compact JSON: {"status":"ok|watch|alert","likely_event":"normal_cycle|door_open|compressor_issue|sensor_fault|unknown","message":"<=120 chars explanation"}. Use only the data provided.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($payload),
                ],
            ],
        ]);

        if (!$response->successful()) {
            Log::warning('Vend temperature AI request failed', [
                'vend_id' => $vend->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (!$content) {
            return null;
        }

        $decision = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::info('Vend temperature AI response not JSON', [
                'vend_id' => $vend->id,
                'content' => $content,
            ]);

            return [
                'payload' => $payload,
                'decision' => [
                    'status' => 'unknown',
                    'likely_event' => 'unknown',
                    'message' => trim($content),
                ],
            ];
        }

        return [
            'payload' => $payload,
            'decision' => $decision,
        ];
    }

    private function recentTemps(Vend $vend, int $windowMinutes, int $maxSamples): Collection
    {
        $query = $vend->vendTemps()
            ->where('type', VendTemp::TYPE_CHAMBER)
            ->where('value', '!=', VendTemp::TEMPERATURE_ERROR)
            ->orderByDesc('created_at');

        if ($windowMinutes > 0) {
            $query->where('created_at', '>=', now()->subMinutes($windowMinutes));
        }

        if ($maxSamples > 0) {
            $query->take($maxSamples);
        }

        return $query
            ->get(['id', 'value', 'created_at'])
            ->reverse()
            ->values();
    }

    private function buildStats(Collection $temps): array
    {
        $first = $temps->first();
        $last = $temps->last();

        $firstTs = optional($first)->created_at;
        $lastTs = optional($last)->created_at;

        $durationSeconds = ($firstTs && $lastTs) ? max(1, $firstTs->diffInSeconds($lastTs)) : 1;
        $trendPerMinute = round((($last->value - $first->value) / self::TEMP_DIVISOR) / ($durationSeconds / 60), 3);

        $values = $temps->pluck('value');

        return [
            'latest_celsius' => round($last->value / self::TEMP_DIVISOR, 2),
            'min_celsius' => round($values->min() / self::TEMP_DIVISOR, 2),
            'max_celsius' => round($values->max() / self::TEMP_DIVISOR, 2),
            'trend_per_minute' => $trendPerMinute,
            'duration_minutes' => round($durationSeconds / 60, 2),
        ];
    }

    private function formatSamples(Collection $temps): array
    {
        return $temps->map(function (VendTemp $temp) {
            return [
                'id' => $temp->id,
                'time' => optional($temp->created_at)->toIso8601String(),
                'celsius' => round($temp->value / self::TEMP_DIVISOR, 2),
            ];
        })->values()->all();
    }

    private function formatSnapshot(array $snapshot, ?VendTemp $latestTemp): array
    {
        $formatted = [];
        $map = [
            't1' => $latestTemp?->value,
            't2' => $snapshot['t2'] ?? null,
            't3' => $snapshot['t3'] ?? null,
            't4' => $snapshot['t4'] ?? null,
        ];

        foreach ($map as $key => $raw) {
            if ($raw === null) {
                continue;
            }

            $formatted[$key] = [
                'raw' => $raw,
                'celsius' => $raw === VendTemp::TEMPERATURE_ERROR
                    ? null
                    : round($raw / self::TEMP_DIVISOR, 2),
            ];
        }

        return $formatted;
    }
}
