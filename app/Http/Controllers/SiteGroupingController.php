<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Operations > Site Grouping — object-oriented management of Site clusters.
 *
 * A "group" (customer_groups row) is a first-class object here: create it,
 * rename it, attach/detach member Sites, delete it. This is the managed
 * alternative to typing the same free-text label into each co-located Site's
 * Edit form.
 *
 * Membership stays EXCLUSIVE and is still stored on customers.customer_group_id
 * (one group per Site), so every existing consumer keeps working unchanged: the
 * Operation Dashboard "Grouped?" travel-together toggle, Customer::siblings(),
 * and the Edit-form field all read/write the same column.
 *
 * Groups are OPERATOR-AGNOSTIC: there is no operator scoping here. Any group can
 * contain any Site, group names are globally unique, and every group/site is
 * visible regardless of operator. (The customer_groups.operator_id column is
 * left untouched for legacy rows but is not used by this page.)
 */
class SiteGroupingController extends Controller
{
    // ── Page ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        return Inertia::render('Vend/SiteGrouping', [
            'groups' => $this->groupsPayload(),
            'filters' => [
                'q' => (string) $request->q,
            ],
        ]);
    }

    // ── Group CRUD ──────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $name = trim($data['name']);

        if ($this->nameTaken($name)) {
            return back()->withErrors(['name' => 'A group with this name already exists.']);
        }

        CustomerGroup::create([
            'name' => $name,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back();
    }

    public function update(Request $request, CustomerGroup $group)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $name = trim($data['name']);

        if ($this->nameTaken($name, $group->id)) {
            return back()->withErrors(['name' => 'A group with this name already exists.']);
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
        // Detach members first so no site is left pointing at a dead group.
        Customer::where('customer_group_id', $group->id)->update(['customer_group_id' => null]);
        $group->delete();

        return back();
    }

    // ── Membership ──────────────────────────────────────────────────────────

    public function addMembers(Request $request, CustomerGroup $group)
    {
        $ids = collect($request->input('customer_ids', []))
            ->map(fn ($v) => (int) $v)->filter()->unique()->all();

        if (empty($ids)) {
            return back();
        }

        Customer::whereIn('id', $ids)->update(['customer_group_id' => $group->id]);

        return back();
    }

    public function removeMember(CustomerGroup $group, Customer $customer)
    {
        if ((int) $customer->customer_group_id === (int) $group->id) {
            $customer->forceFill(['customer_group_id' => null])->saveQuietly();
        }

        return back();
    }

    // ── Site picker (XHR/JSON) ──────────────────────────────────────────────

    public function searchSites(Request $request)
    {
        $q = trim((string) $request->q);

        $query = Customer::query()
            ->whereNull('customer_group_id'); // only unassigned sites are attachable

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
            ->get(['id', 'name', 'status_id', 'code']);

        $vendCodes = $this->vendCodeMap($rows->pluck('id')->all());

        return response()->json([
            'sites' => $rows->map(fn ($c) => $this->siteRow($c, $vendCodes))->values(),
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function groupsPayload(): array
    {
        $groups = CustomerGroup::query()
            ->orderBy('name')
            ->get(['id', 'name', 'notes']);

        $memberRows = Customer::query()
            ->whereIn('customer_group_id', $groups->pluck('id')->all() ?: [-1])
            ->orderBy('name')
            ->get(['id', 'name', 'status_id', 'customer_group_id', 'code']);

        $vendCodes = $this->vendCodeMap($memberRows->pluck('id')->all());
        $membersByGroup = $memberRows->groupBy('customer_group_id');

        return $groups->map(function ($g) use ($membersByGroup, $vendCodes) {
            $members = ($membersByGroup[$g->id] ?? collect())
                ->map(fn ($c) => $this->siteRow($c, $vendCodes))
                ->values();

            return [
                'id' => $g->id,
                'name' => $g->name,
                'notes' => $g->notes,
                'members' => $members,
                'member_count' => $members->count(),
            ];
        })->values()->all();
    }

    private function siteRow($c, array $vendCodes = []): array
    {
        // Site ID = ref_id = customers.id + 20000, uniformly, matching the
        // app-wide Site ID standard (which replaced CMS virtual_customer_code).
        return [
            'id' => $c->id,
            'name' => $c->name,
            'site_id' => (string) ($c->id + 20000),
            // Machine ID = the site's (latest) bound vend code — what admins
            // recognise a machine by on the Operation Dashboard.
            'machine_id' => $vendCodes[$c->id] ?? null,
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

    private function nameTaken(string $name, ?int $exceptId = null): bool
    {
        return CustomerGroup::query()
            ->where('name', $name)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }
}
