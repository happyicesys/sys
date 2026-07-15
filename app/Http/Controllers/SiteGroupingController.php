<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Operator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Operations > Site Grouping — object-oriented management of Site clusters.
 *
 * A "group" (customer_groups row) is treated as a first-class object here:
 * create it, rename it, attach/detach member Sites, delete it. This is the
 * managed alternative to the old workflow of typing the same free-text label
 * into each co-located Site's Edit form.
 *
 * Membership stays EXCLUSIVE and is still stored on customers.customer_group_id
 * (one group per Site), so every existing consumer keeps working unchanged:
 * the Operation Dashboard "Grouped?" travel-together toggle, Customer::siblings(),
 * and the Edit-form field all read/write the same column. This page never
 * changes the data model — only the way groups are curated.
 *
 * Scoping mirrors HasFilter::filterOperatorDB: operator 1 (HappyIce) sees every
 * operator's groups; any other user is limited to their own operator_id. Writes
 * are guarded so a scoped user can only touch groups/sites under their operator.
 */
class SiteGroupingController extends Controller
{
    // ── Page ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        return Inertia::render('Vend/SiteGrouping', [
            'groups' => $this->groupsPayload($request),
            'operatorOptions' => $this->operatorOptions(),
            'canPickOperator' => $this->isHappyIce(),
            'filters' => [
                'operator_ids' => $this->requestOperatorIds($request),
                'q' => (string) $request->q,
            ],
        ]);
    }

    // ── Group CRUD ──────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'operator_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $operatorId = $this->resolveOperatorId($data['operator_id'] ?? null);
        $name = trim($data['name']);

        if ($this->nameTaken($operatorId, $name)) {
            return back()->withErrors(['name' => 'A group with this name already exists for this operator.']);
        }

        CustomerGroup::create([
            'name' => $name,
            'operator_id' => $operatorId,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back();
    }

    public function update(Request $request, CustomerGroup $group)
    {
        $this->authorizeGroup($group);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $name = trim($data['name']);

        if ($this->nameTaken($group->operator_id, $name, $group->id)) {
            return back()->withErrors(['name' => 'A group with this name already exists for this operator.']);
        }

        $group->update([
            'name' => $name,
            'notes' => $data['notes'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        return back();
    }

    public function destroy(CustomerGroup $group)
    {
        $this->authorizeGroup($group);

        // Detach members first so no site is left pointing at a dead group.
        Customer::where('customer_group_id', $group->id)->update(['customer_group_id' => null]);
        $group->delete();

        return back();
    }

    // ── Membership ──────────────────────────────────────────────────────────

    public function addMembers(Request $request, CustomerGroup $group)
    {
        $this->authorizeGroup($group);

        $ids = collect($request->input('customer_ids', []))
            ->map(fn ($v) => (int) $v)->filter()->unique()->all();

        if (empty($ids)) {
            return back();
        }

        $query = Customer::whereIn('id', $ids);
        // Only sites the caller may see, and — to keep a cluster single-operator
        // — only sites matching the group's operator when the group has one.
        if (! $this->isHappyIce()) {
            $query->where('operator_id', (int) auth()->user()->operator_id);
        }
        if ($group->operator_id) {
            $query->where('operator_id', $group->operator_id);
        }

        $query->update(['customer_group_id' => $group->id]);

        return back();
    }

    public function removeMember(CustomerGroup $group, Customer $customer)
    {
        $this->authorizeGroup($group);

        if ((int) $customer->customer_group_id === (int) $group->id) {
            $customer->forceFill(['customer_group_id' => null])->saveQuietly();
        }

        return back();
    }

    // ── Site picker (XHR/JSON) ──────────────────────────────────────────────

    public function searchSites(Request $request)
    {
        $q = trim((string) $request->q);
        $group = $request->filled('group_id') ? CustomerGroup::find((int) $request->group_id) : null;
        $opIds = $this->scopedOperatorIds();

        $query = Customer::query()
            ->whereNull('customer_group_id') // only unassigned sites are attachable
            ->when($opIds !== null, fn ($x) => $x->whereIn('operator_id', $opIds));

        // Constrain to the group's operator so the cluster stays single-operator.
        if ($group && $group->operator_id) {
            $query->where('operator_id', $group->operator_id);
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('virtual_customer_code', 'like', "%{$q}%")
                    // Machines whose vend code matches, so admins can find a site
                    // by the Machine ID they recognise (vends.code).
                    ->orWhereIn('customers.id', function ($sub) use ($q) {
                        $sub->select('customer_id')->from('vends')->where('code', 'like', "%{$q}%");
                    });
                if (ctype_digit($q)) {
                    $w->orWhere('id', ((int) $q) - 20000);
                }
            });
        }

        $rows = $query->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'operator_id', 'status_id', 'virtual_customer_code', 'person_id', 'code']);

        $operatorNames = Operator::pluck('name', 'id');
        $vendCodes = $this->vendCodeMap($rows->pluck('id')->all());

        return response()->json([
            'sites' => $rows->map(fn ($c) => $this->siteRow($c, $operatorNames, $vendCodes))->values(),
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function groupsPayload(Request $request): array
    {
        $opIds = $this->scopedOperatorIds();

        $filterOpIds = $this->requestOperatorIds($request);

        $groups = CustomerGroup::query()
            ->when($opIds !== null, fn ($q) => $q->whereIn('operator_id', $opIds))
            ->when(! empty($filterOpIds), fn ($q) => $q->whereIn('operator_id', $filterOpIds))
            ->orderBy('name')
            ->get(['id', 'name', 'operator_id', 'notes']);

        $operatorNames = Operator::pluck('name', 'id');

        $memberRows = Customer::query()
            ->whereIn('customer_group_id', $groups->pluck('id')->all() ?: [-1])
            ->orderBy('name')
            ->get(['id', 'name', 'operator_id', 'status_id', 'customer_group_id', 'virtual_customer_code', 'person_id', 'code']);

        $vendCodes = $this->vendCodeMap($memberRows->pluck('id')->all());
        $membersByGroup = $memberRows->groupBy('customer_group_id');

        return $groups->map(function ($g) use ($membersByGroup, $operatorNames, $vendCodes) {
            $members = ($membersByGroup[$g->id] ?? collect())
                ->map(fn ($c) => $this->siteRow($c, $operatorNames, $vendCodes))
                ->values();

            return [
                'id' => $g->id,
                'name' => $g->name,
                'operator_id' => $g->operator_id,
                'operator_name' => $g->operator_id ? ($operatorNames[$g->operator_id] ?? null) : null,
                'notes' => $g->notes,
                'members' => $members,
                'member_count' => $members->count(),
            ];
        })->values()->all();
    }

    private function siteRow($c, $operatorNames, array $vendCodes = []): array
    {
        // Site ID = ref_id = customers.id + 20000, uniformly, matching the
        // app-wide Site ID standard (which replaced CMS virtual_customer_code).
        // So every site always has a Site ID — no blanks for CMS-linked sites.
        $siteId = $c->id + 20000;

        return [
            'id' => $c->id,
            'name' => $c->name,
            'site_id' => (string) $siteId,
            // Machine ID = the site's (latest) bound vend code — what admins
            // recognise a machine by on the Operation Dashboard.
            'machine_id' => $vendCodes[$c->id] ?? null,
            'operator_id' => $c->operator_id,
            'operator_name' => $c->operator_id ? ($operatorNames[$c->operator_id] ?? null) : null,
            'status_id' => $c->status_id,
            'status_name' => Customer::STATUSES_MAPPING[$c->status_id] ?? null,
        ];
    }

    /**
     * customer_id => latest vend code (begin_date, then created_at desc), for a
     * small set of customers. Pure lookup — the sets here (group members or ≤30
     * search hits) are tiny, so first-seen-per-customer in PHP is cheap.
     *
     * @param  int[]  $customerIds
     * @return array<int,string>
     */
    private function vendCodeMap(array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }

        $rows = DB::table('vends')
            ->whereIn('customer_id', $customerIds)
            ->orderBy('customer_id')
            ->orderByDesc('begin_date')
            ->orderByDesc('created_at')
            ->get(['customer_id', 'code']);

        $map = [];
        foreach ($rows as $r) {
            if (! isset($map[$r->customer_id])) {
                $map[$r->customer_id] = $r->code;
            }
        }

        return $map;
    }

    private function operatorOptions()
    {
        $opIds = $this->scopedOperatorIds();

        return Operator::query()
            ->when($opIds !== null, fn ($q) => $q->whereIn('id', $opIds))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Normalise the operator_ids[] filter to a clean int list. */
    private function requestOperatorIds(Request $request): array
    {
        return collect($request->input('operator_ids', []))
            ->map(fn ($v) => (int) $v)->filter()->unique()->values()->all();
    }

    private function nameTaken(?int $operatorId, string $name, ?int $exceptId = null): bool
    {
        return CustomerGroup::query()
            ->where('operator_id', $operatorId)
            ->where('name', $name)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /**
     * A scoped (non-HappyIce) user can only ever create/own groups under their
     * own operator; HappyIce may target any operator (or leave it blank).
     */
    private function resolveOperatorId($requested): ?int
    {
        if (! $this->isHappyIce()) {
            return (int) auth()->user()->operator_id;
        }

        return ($requested !== null && $requested !== '') ? (int) $requested : null;
    }

    private function authorizeGroup(CustomerGroup $group): void
    {
        if ($this->isHappyIce()) {
            return;
        }
        if ((int) $group->operator_id !== (int) auth()->user()->operator_id) {
            abort(403);
        }
    }

    private function isHappyIce(): bool
    {
        return auth()->check() && (int) auth()->user()->operator_id === 1;
    }

    /** @return int[]|null  null => every operator (HappyIce) */
    private function scopedOperatorIds(): ?array
    {
        if ($this->isHappyIce()) {
            return null;
        }

        return [(int) auth()->user()->operator_id];
    }
}
