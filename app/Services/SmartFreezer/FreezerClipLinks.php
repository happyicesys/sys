<?php

namespace App\Services\SmartFreezer;

use App\Models\SmartFreezerClip;
use Illuminate\Support\Collection;

/**
 * Our own door-session recordings for a page of rows, as play links — one indexed query per page.
 *
 * A clip is keyed by (vend, session ref); a sale carries the ref as its TRADE's `SFREF`, a
 * recognition as `session_ref`. Rows of other machine kinds carry no ref and cost nothing.
 */
class FreezerClipLinks
{
    /**
     * @param  iterable<object>  $sales  rows with `id`, `vend_id` and `vend_transaction_json`
     * @return array<int, list<array<string, mixed>>> sale id => clip links
     */
    public function forSales(iterable $sales): array
    {
        $keys = [];
        foreach ($sales as $sale) {
            $ref = $this->sessionRef($sale->vend_transaction_json ?? null);
            if ($ref !== null && ! empty($sale->vend_id)) {
                $keys[$sale->id] = [(int) $sale->vend_id, $ref];
            }
        }

        return $this->linksFor($keys);
    }

    /**
     * @param  array<int|string, array{0: int, 1: string}>  $keys  any row key => [vend id, session ref]
     * @return array<int|string, list<array<string, mixed>>> row key => clip links (rows with none omitted)
     */
    public function linksFor(array $keys): array
    {
        $keys = array_filter($keys, fn ($k) => ! empty($k[0]) && is_string($k[1]) && $k[1] !== '');
        if ($keys === []) {
            return [];
        }

        /** @var Collection<string, Collection<int, SmartFreezerClip>> $clips */
        $clips = SmartFreezerClip::query()
            ->whereIn('session_ref', collect($keys)->pluck(1)->unique()->values())
            ->orderBy('camera_id')
            ->get(['id', 'vend_id', 'session_ref', 'camera_id', 'bytes', 'duration_ms'])
            ->groupBy(fn (SmartFreezerClip $c) => $c->vend_id.'|'.$c->session_ref);

        $out = [];
        foreach ($keys as $rowKey => [$vendId, $ref]) {
            $found = $clips->get($vendId.'|'.$ref);
            if ($found !== null && $found->isNotEmpty()) {
                $out[$rowKey] = $found->map(fn (SmartFreezerClip $c) => $c->toLink())->values()->all();
            }
        }

        return $out;
    }

    private function sessionRef(mixed $json): ?string
    {
        if (is_string($json)) {
            $json = json_decode($json, true);
        }
        $ref = is_array($json) ? ($json['SFREF'] ?? null) : null;

        return is_string($ref) && $ref !== '' ? $ref : null;
    }
}
