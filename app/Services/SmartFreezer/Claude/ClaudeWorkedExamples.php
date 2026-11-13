<?php

namespace App\Services\SmartFreezer\Claude;

use App\Models\Product;
use App\Models\SmartFreezerRecognition;
use App\Models\VendTransaction;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The worked examples sent with each Claude count (Brian, 2026-11-13: "should I train you or give more info?"). Claude
 * cannot be trained on our videos, so what it learns about these cameras goes in with every call: a few door sessions
 * listed in `smart_freezer.claude_recognition.examples`, each with its frames and the count a person confirmed.
 *
 * The confirmed count is the person's Adjust Qty (`meta_json.correction.basket`), else Zijia's count (a session is only
 * listed after a person checked it). A session is never its own example, and the freezer's own sessions come first:
 * every freezer's camera sits differently. Frames are cut once per session and kept a day in the file cache.
 */
class ClaudeWorkedExamples
{
    public function __construct(private readonly DoorFrameSource $frames) {}

    /** @return list<WorkedExample> */
    public function for(SmartFreezerRecognition $session): array
    {
        $config = (array) config('smart_freezer.claude_recognition');
        $ids = array_values(array_diff(array_map('intval', (array) ($config['examples'] ?? [])), [$session->id]));
        $max = max(0, (int) ($config['max_examples'] ?? 2));
        if ($ids === [] || $max === 0) {
            return [];
        }

        $candidates = SmartFreezerRecognition::with(['vend', 'videos'])->whereIn('id', $ids)->get()
            ->reject(fn (SmartFreezerRecognition $r) => $session->session_ref !== null && $r->session_ref === $session->session_ref)
            ->sortBy(fn (SmartFreezerRecognition $r) => [$r->vend_id === $session->vend_id ? 0 : 1, array_search($r->id, $ids, true)])
            ->values();

        $examples = [];
        foreach ($candidates as $recognition) {
            if (count($examples) >= $max) {
                break;
            }
            $example = $this->example($recognition, (int) ($config['example_frames'] ?? 8));
            if ($example !== null) {
                $examples[] = $example;
            }
        }

        return $examples;
    }

    /**
     * The confirmed count of a session: a person's Adjust Qty, else Zijia's. Null when neither exists.
     *
     * @return array{0: array<int, int>, 1: string}|null [product id => units, source]
     */
    public static function confirmedCount(SmartFreezerRecognition $recognition): ?array
    {
        $meta = $recognition->vend_transaction_id
            ? VendTransaction::withoutGlobalScopes()->find($recognition->vend_transaction_id, ['id', 'meta_json'])?->meta_json
            : null;
        $meta = is_array($meta) ? $meta : (is_string($meta) ? (array) json_decode($meta, true) : []);
        $basket = $meta['correction']['basket'] ?? null;
        if (is_array($basket)) {
            return [self::byProduct($basket), 'person'];
        }
        if ($recognition->verdict === null) {
            return null;
        }
        $count = [];
        foreach ((array) $recognition->verdict_lines as $line) {
            $id = (int) ($line['product_id'] ?? 0);
            if ($id > 0) {
                $count[$id] = ($count[$id] ?? 0) + (int) ($line['taken'] ?? 0);
            }
        }

        return [self::byProduct($count), 'zijia'];
    }

    /** @return array<int, int> */
    private static function byProduct(array $count): array
    {
        $out = [];
        foreach ($count as $key => $units) {
            $id = is_numeric($key) ? (int) $key : (int) ltrim((string) $key, 'p');
            if ($id > 0 && (int) $units > 0) {
                $out[$id] = (int) $units;
            }
        }
        ksort($out);

        return $out;
    }

    private function example(SmartFreezerRecognition $recognition, int $frames): ?WorkedExample
    {
        $confirmed = self::confirmedCount($recognition);
        if ($confirmed === null) {
            return null;
        }
        [$answer, $source] = $confirmed;
        $key = 'claude-freezer-example:v1:'.$recognition->id.':'.md5(json_encode([$frames,
            config('smart_freezer.claude_recognition.closeups'), config('smart_freezer.claude_recognition.frame_height_px'),
            config('smart_freezer.claude_recognition.crop_width'), config('smart_freezer.claude_recognition.cameras')]));

        $cached = Cache::store('file')->get($key);
        if (! is_array($cached)) {
            try {
                $cut = $this->frames->framesFor($recognition, $frames)['frames'];
            } catch (Throwable $e) {
                report($e);

                return null;
            }
            if ($cut === []) {
                return null;
            }
            $cached = array_map(fn (DoorFrame $f) => [$f->camera, $f->atMs, $f->videoMs, base64_encode($f->jpeg), $f->kind], $cut);
            Cache::store('file')->put($key, $cached, now()->addDay());
        }

        $names = Product::withoutGlobalScopes()->whereIn('id', array_keys($answer))->pluck('name', 'id')->all();

        return new WorkedExample(
            recognitionId: $recognition->id,
            label: ($recognition->session_ref ?? $recognition->trade_id).' on machine '.($recognition->vend?->code ?? '?'),
            frames: array_map(fn (array $f) => new DoorFrame($f[0], $f[1], $f[2], (string) base64_decode($f[3]), $f[4]), $cached),
            answer: $answer,
            names: array_map('strval', $names),
            source: $source,
        );
    }
}
