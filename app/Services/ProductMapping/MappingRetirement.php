<?php

namespace App\Services\ProductMapping;

use App\Models\ProductMapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deactivates a mapping once every machine has moved off it (Brian, 2026-11-21: "本来有机器, 过后被 assign 了一个
 * upcoming mapping, 全部搬走了, 那这个 current 在三天后自动 deactivate, 不是 delete"). Ops did it by hand, one
 * mapping at a time.
 *
 * A mapping retires when ALL of these hold:
 *  - it is active and has an upcoming mapping of its own (the changeover was planned);
 *  - no machine is bound to it, whatever the machine's status (an inactive or factory machine still on it has not
 *    moved — Product Mapping's "Machine(s)" with Machine Status "All" reads 0);
 *  - no machine is still waiting to move ONTO it (its own upcoming, or its mapping's upcoming, names this one — the
 *    "Machine(s) at upcoming stage" figure), so a mapping that is still a step in a chain stays;
 *  - a machine is KNOWN to have left it, and the last one left at least RETIRE_AFTER_DAYS ago. Known = the
 *    `last_machine_left_at` stamp (every rebind since 2026-11-21) or the user_logs audit (web rebinds since
 *    2026-07-01). A mapping no machine was ever seen leaving (a fresh or replicated copy) is never touched.
 *
 * Deactivate only: the mapping, its items and its history stay, and anyone may switch it on again (it then retires
 * again RETIRE_AFTER_DAYS after its next machine leaves, or after the stamp it already has if it stays empty — so
 * a person who wants an empty mapping kept active removes its upcoming mapping).
 */
class MappingRetirement
{
    public const RETIRE_AFTER_DAYS = 3;

    public const LOG_SOURCE = 'mapping-retire';

    /**
     * Record that machines just left these mappings. Called for every rebind: the Vend model hook (Eloquent saves)
     * and the VM Binding bulk update, which fires no model event. A plain update: no updated_at, no audit row.
     *
     * @param  iterable<int|string|null>  $mappingIds
     */
    public static function machinesLeft(iterable $mappingIds): void
    {
        $ids = collect($mappingIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        try {
            DB::table('product_mappings')->whereIn('id', $ids)->update(['last_machine_left_at' => now()]);
        } catch (\Throwable $e) {
            // Never fail a machine save over this (e.g. the column not migrated yet during a deploy).
            Log::warning('mapping retirement: could not stamp machine left', ['ids' => $ids->all(), 'error' => $e->getMessage()]);
        }
    }

    /**
     * Active mappings that meet every rule but the waiting time, with when their last machine left.
     * Pass ids to look only at those (the index page's rows).
     *
     * @param  array<int>|null  $onlyIds
     * @return Collection<int, object{id:int, name:string, left_at:Carbon}> keyed by mapping id
     */
    public function emptied(?array $onlyIds = null): Collection
    {
        if ($onlyIds !== null && $onlyIds === []) {
            return collect();
        }

        // Raw NOT EXISTS, never whereDoesntHave(): Vend's operator scope would make "no machine" mean "no machine
        // I may see" (CLAUDE.md, operator isolation).
        $rows = DB::table('product_mappings as pm')
            ->where('pm.is_active', true)
            ->whereNotNull('pm.upcoming_product_mapping_id')
            ->whereColumn('pm.upcoming_product_mapping_id', '<>', 'pm.id')
            ->when($onlyIds !== null, fn ($q) => $q->whereIn('pm.id', $onlyIds))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('vends as v')->whereColumn('v.product_mapping_id', 'pm.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('vends as v')
                ->leftJoin('product_mappings as cur', 'cur.id', '=', 'v.product_mapping_id')
                ->whereRaw('coalesce(v.upcoming_product_mapping_id, cur.upcoming_product_mapping_id) = pm.id'))
            ->get(['pm.id', 'pm.name', 'pm.last_machine_left_at']);

        if ($rows->isEmpty()) {
            return collect();
        }

        $logged = $this->lastLeftFromAudit($rows->pluck('id')->all());

        return $rows->map(function ($row) use ($logged) {
            $times = array_filter([
                $row->last_machine_left_at ? Carbon::parse($row->last_machine_left_at) : null,
                $logged[$row->id] ?? null,
            ]);
            if ($times === []) {
                return null;
            }

            return (object) [
                'id' => (int) $row->id,
                'name' => $row->name,
                'left_at' => collect($times)->max(),
            ];
        })->filter()->keyBy('id');
    }

    /** When each emptied mapping will be (or should already have been) deactivated, for the index page. */
    public function retireDates(array $mappingIds): array
    {
        return $this->emptied($mappingIds)
            ->map(fn ($m) => $m->left_at->copy()->addDays(self::RETIRE_AFTER_DAYS))
            ->all();
    }

    /**
     * Deactivate every mapping whose last machine left RETIRE_AFTER_DAYS ago or more.
     *
     * @return Collection<int, object> the mappings deactivated (or, dry run, that would be)
     */
    public function run(bool $apply = true, ?Carbon $now = null): Collection
    {
        $now ??= now();
        $due = $this->emptied()->filter(fn ($m) => $m->left_at->lte($now->copy()->subDays(self::RETIRE_AFTER_DAYS)))->values();

        if (! $apply) {
            return $due;
        }

        return $due->filter(function ($m) use ($now) {
            // Conditional on everything still holding: a machine bound or a person's change since the read wins.
            $updated = DB::table('product_mappings as pm')
                ->where('pm.id', $m->id)
                ->where('pm.is_active', true)
                ->whereNotNull('pm.upcoming_product_mapping_id')
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('vends as v')->whereColumn('v.product_mapping_id', 'pm.id'))
                ->update(['pm.is_active' => false, 'pm.updated_at' => $now]);
            if ($updated === 0) {
                return false;
            }

            // Console writes are not audited by UserLogger; record this one by hand so the mapping's history says
            // why it went inactive.
            DB::table('user_logs')->insert([
                'user_id' => null,
                'user_name' => 'Auto: all machines moved',
                'event' => 'updated',
                'auditable_type' => ProductMapping::class,
                'auditable_id' => $m->id,
                'changes' => json_encode(['is_active' => [true, false]]),
                'source' => self::LOG_SOURCE,
                'ip' => null,
                'url' => 'product-mappings:retire-emptied',
                'created_at' => $now,
            ]);
            Log::info('mapping retired: all machines moved', [
                'product_mapping_id' => $m->id,
                'name' => $m->name,
                'last_machine_left_at' => $m->left_at->toDateTimeString(),
            ]);

            return true;
        })->values();
    }

    /**
     * Last time the audit saw a machine's product_mapping_id change FROM each mapping.
     *
     * @return array<int, Carbon>
     */
    private function lastLeftFromAudit(array $mappingIds): array
    {
        // A Vend 'updated' row stores {"product_mapping_id": [from, to]}; values may be ints or strings.
        $from = "cast(json_unquote(json_extract(changes, '$.product_mapping_id[0]')) as unsigned)";

        return DB::table('user_logs')
            ->where('auditable_type', \App\Models\Vend::class)
            ->where('event', 'updated')
            ->whereRaw("json_contains_path(changes, 'one', '$.product_mapping_id')")
            ->whereIn(DB::raw($from), array_map('intval', $mappingIds))
            ->groupBy(DB::raw($from))
            ->selectRaw("{$from} as mapping_id, max(created_at) as left_at")
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->mapping_id => Carbon::parse($r->left_at)])
            ->all();
    }
}
