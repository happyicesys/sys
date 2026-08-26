<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Merges duplicate vend_channels rows — rows sharing one (vend_id, code) pair.
 *
 * Duplicates were born from races and malformed channel codes in the channel
 * report path before the unique (vend_id, code) index existed (worked example:
 * vend 4753 / code 17, 2026-08-03 — a second row was created and from then on
 * received every report update while the original froze mid-restock, inflating
 * the machine's stock and capacity totals).
 *
 * Merge policy, per duplicate group:
 *  - SURVIVOR  = the lowest id. It is the oldest row and therefore the one the
 *    bulk of historical foreign keys (transactions, error logs) already point
 *    to, so keeping it repoints the fewest rows.
 *  - DONOR     = the most recently updated row (tie: highest id). Reports key
 *    channels by code and the last row in PK order wins the update, so this is
 *    the row holding the machine's CURRENT qty / capacity / price state.
 *  - The donor's live state is copied onto the survivor, every table carrying
 *    a vend_channel_id column is repointed from the losers to the survivor,
 *    and the losers are deleted. All of it in one transaction per group.
 */
class VendChannelDuplicateResolver
{
    /**
     * The "current machine state" columns copied from donor to survivor.
     * Identity (id, vend_id, code) and created_at stay the survivor's own.
     */
    public const STATE_COLUMNS = [
        'qty',
        'capacity',
        'amount',
        'amount2',
        'is_active',
        'product_id',
        'sku_code',
        'discount_group',
        'error_rate_json',
        'locked_qty',
        'qty_sold_at',
        'qty_restocked_at',
        'qty_not_available_duration',
    ];

    /** @var list<string>|null */
    private ?array $referencingTables = null;

    /**
     * Every duplicate group, as raw rows grouped by "vend_id:code".
     *
     * @return Collection<string, Collection<int, object>>
     */
    public function duplicateGroups(): Collection
    {
        $pairs = DB::table('vend_channels')
            ->select('vend_id', 'code')
            ->groupBy('vend_id', 'code')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($pairs->isEmpty()) {
            return collect();
        }

        return DB::table('vend_channels')
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($q) use ($pair) {
                        $q->where('vend_id', $pair->vend_id)->where('code', $pair->code);
                    });
                }
            })
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => $row->vend_id.':'.$row->code);
    }

    /**
     * Merge every duplicate group. Dry-run unless $apply — the returned plan is
     * identical either way, so callers can print it before committing.
     *
     * @return list<array{vend_id: int, code: int, survivor: int, donor: int, deleted: list<int>, repointed: array<string, int>}>
     */
    public function resolve(bool $apply = false): array
    {
        return $this->duplicateGroups()
            ->map(fn (Collection $rows) => $this->mergeGroup($rows, $apply))
            ->values()
            ->all();
    }

    /**
     * Tables (other than vend_channels) carrying a vend_channel_id column,
     * discovered from the live schema so a future referencing table cannot be
     * silently missed.
     *
     * @return list<string>
     */
    public function referencingTables(): array
    {
        return $this->referencingTables ??= DB::table('information_schema.COLUMNS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('COLUMN_NAME', 'vend_channel_id')
            ->where('TABLE_NAME', '!=', 'vend_channels')
            ->orderBy('TABLE_NAME')
            ->pluck('TABLE_NAME')
            ->all();
    }

    /**
     * @param  Collection<int, object>  $rows  one duplicate group, ordered by id
     * @return array{vend_id: int, code: int, survivor: int, donor: int, deleted: list<int>, repointed: array<string, int>}
     */
    private function mergeGroup(Collection $rows, bool $apply): array
    {
        $survivor = $rows->first();
        $donor = $rows
            ->sortByDesc(fn ($row) => [(string) $row->updated_at, $row->id])
            ->first();
        $loserIds = $rows->pluck('id')->reject(fn ($id) => $id === $survivor->id)->values()->all();

        $repointed = [];
        if ($apply) {
            DB::transaction(function () use ($survivor, $donor, $loserIds, &$repointed) {
                foreach ($this->referencingTables() as $table) {
                    $repointed[$table] = DB::table($table)
                        ->whereIn('vend_channel_id', $loserIds)
                        ->update(['vend_channel_id' => $survivor->id]);
                }

                // Raw-to-raw copy (donor came from the query builder), so casted
                // columns like error_rate_json cross without re-encoding.
                DB::table('vend_channels')
                    ->where('id', $survivor->id)
                    ->update(Arr::only((array) $donor, self::STATE_COLUMNS) + [
                        'updated_at' => Carbon::now(),
                    ]);

                DB::table('vend_channels')->whereIn('id', $loserIds)->delete();
            });
        } else {
            foreach ($this->referencingTables() as $table) {
                $repointed[$table] = DB::table($table)->whereIn('vend_channel_id', $loserIds)->count();
            }
        }
        $repointed = array_filter($repointed);

        return [
            'vend_id' => $survivor->vend_id,
            'code' => $survivor->code,
            'survivor' => $survivor->id,
            'donor' => $donor->id,
            'deleted' => $loserIds,
            'repointed' => $repointed,
        ];
    }
}
