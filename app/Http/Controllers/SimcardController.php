<?php

namespace App\Http\Controllers;

use App\Http\Resources\SimcardResource;
use App\Http\Resources\TelcoResource;
use App\Models\Simcard;
use App\Models\Telco;
use App\Models\Vend;
use App\Support\SiteSearch;
use App\Traits\ExportOptimizationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Rap2hpoutre\FastExcel\FastExcel;

class SimcardController extends Controller
{
    use ExportOptimizationTrait;

    /**
     * The Status column's filter options — the only two values the telco APIs
     * have ever reported (VoicePing package status). Kept here so the dropdown
     * and the filter can never drift apart.
     */
    public const USAGE_STATUSES = ['Activated', 'Inactive'];

    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;

        return Inertia::render('Simcard/Index', [
            'simcards' => SimcardResource::collection(
                $this->filteredQuery($request)
                    ->with([
                        'operator',
                        'telco',
                        // Only what the Index page reads per bound machine
                        // (Machine ID / Machine APK / Signal Strength columns)
                        // — a bare 'vends' serialized FULL vend models into the
                        // Inertia payload, dragging apk_ver_json siblings like
                        // settings_parameter_json and meta_json along for every
                        // row on the page.
                        // is_online + last_updated_at back the Online/Offline line
                        // in Signal Strength: SyncOnlineStatus flips is_online off
                        // when the machine's HTTP heartbeat goes quiet for 15 min,
                        // and a null last_updated_at means it never checked in at
                        // all (reads 'N/A', not 'Offline').
                        'vends:id,simcard_id,code,customer_id,apk_version_code,apk_ver_json,'
                            .'is_online,last_updated_at,'
                            .'internet_source,internet_provider,internet_network,'
                            .'internet_signal,internet_signal_max',
                        // Site column — id + name only; ref_id is id + 20000.
                        'vends.customer:id,name',
                        'updatedBy:id,name',
                    ])
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'telcos' => TelcoResource::collection(Telco::orderBy('name')->get()),
            'usageStatusOptions' => self::USAGE_STATUSES,
            'vends' => Vend::with(['vendPrefix', 'customer'])->select('id', 'code', 'simcard_id', 'name', 'vend_prefix_id', 'customer_id')->orderBy('code')->get(),
        ]);
    }

    /**
     * Simcard Index ▸ "Excel" — the grid as an .xlsx, same filters, same sort,
     * same columns, ignoring pagination. Column order and cell contents mirror
     * the table one-for-one; the columns that stack one line per bound machine
     * on screen (Machine ID / Site / Machine APK / Signal Strength) stack the
     * same lines inside a single cell.
     */
    public function exportExcel(Request $request)
    {
        // Eager-loaded ->get() rather than the usual cursor(): cursor() does not
        // eager load, and this table is a few hundred rows — an N+1 per row over
        // vends/customers/telco would cost far more than holding them in memory.
        $simcards = $this->filteredQuery($request)
            ->with(['telco:id,name', 'updatedBy:id,name', 'vends', 'vends.customer:id,name'])
            ->get();

        $row = 0;

        return (new FastExcel($this->yieldOneByOne($simcards)))->download(
            $this->formatExportFilename('Simcards', 'xlsx'),
            function ($simcard) use (&$row) {
                $row++;

                return [
                    '#' => $row,
                    'Simcard Number' => $simcard->code,
                    'Machine ID' => $this->exportLines($simcard, fn ($vend) => $vend->code),
                    'Site' => $this->exportLines($simcard, fn ($vend) => $vend->customer
                        ? ($vend->customer->id + \App\Models\Customer::RUNNING_NUMBER_INIT).' '.$vend->customer->name
                        : null),
                    'Machine APK' => $this->exportLines(
                        $simcard,
                        fn ($vend) => ($version = $vend->reportedApkVersion()) > 0 ? $version : null
                    ),
                    'SimCard Package' => $simcard->telco?->name,
                    'Signal Strength' => $this->exportSignal($simcard),
                    'Updated By' => $simcard->updatedBy
                        ? $simcard->updatedBy->name."\n".optional($simcard->updated_at)->format('ymd h:i a')
                        : null,
                    'Status' => $this->exportStatus($simcard),
                ];
            }
        );
    }

    /**
     * One line per bound machine, in the same order as the on-screen stacks.
     * Machines the callback has nothing to say about become an em dash so the
     * lines stay aligned with the Machine ID cell beside them.
     */
    private function exportLines(Simcard $simcard, callable $value): ?string
    {
        if ($simcard->vends->isEmpty()) {
            return null;
        }

        return $simcard->vends
            ->map(fn ($vend) => $value($vend) ?? '—')
            ->implode("\n");
    }

    /** The Signal Strength cell: Online/Offline per machine, then the reported link. */
    private function exportSignal(Simcard $simcard): ?string
    {
        $lines = $simcard->vends->map(function ($vend) {
            if (! $vend->last_updated_at) {
                return 'N/A';
            }

            return $vend->is_online ? 'Online' : 'Offline';
        })->all();

        // The Vue reads the first bound machine that has ever reported a link.
        $link = $simcard->vends->first(fn ($vend) => $vend->internet_source);

        if ($link) {
            $lines[] = $this->internetLinkTitle($link);
            $bars = $this->signalLevel($link);
            if ($bars !== null) {
                $lines[] = $bars.'/5';
            } elseif ($link->internet_source === 'none') {
                $lines[] = 'No Link';
            }
        }

        return $lines ? implode("\n", $lines) : null;
    }

    /** The Status cell: the same badges the column stacks, one per line. */
    private function exportStatus(Simcard $simcard): ?string
    {
        if (! $simcard->usage_status) {
            return null;
        }

        $lines = [$simcard->usage_status];

        if ($simcard->usage_active_at) {
            $lines[] = 'Act '.$simcard->usage_active_at->format('ymd');
        }
        if ($simcard->usage_expire_at) {
            $lines[] = 'Exp '.$simcard->usage_expire_at->format('ymd');
        }
        if ($simcard->usage_used_mb !== null) {
            $lines[] = sprintf('%.2f MB', $simcard->usage_used_mb);
        }

        return implode("\n", $lines);
    }

    /** PHP twin of resources/js/constants/internetLink.js internetLinkTitle(). */
    private function internetLinkTitle($vend): string
    {
        return match ($vend->internet_source) {
            'none' => 'No Link',
            'lan' => 'LAN',
            'wifi' => $vend->internet_provider ? 'Wi-Fi '.$vend->internet_provider : 'Wi-Fi',
            'telco' => trim(($vend->internet_provider ?: 'Telco').' '.$vend->internet_network),
            default => $vend->internet_provider ?: ($vend->internet_network ?: 'Internet'),
        };
    }

    /** PHP twin of internetLink.js signalLevel() — normalised to the 5-bar scale. */
    private function signalLevel($vend): ?int
    {
        if ($vend->internet_signal === null) {
            return null;
        }

        $max = $vend->internet_signal_max ?: 5;

        return $max === 5 ? (int) $vend->internet_signal : (int) round($vend->internet_signal / $max * 5);
    }

    /**
     * The one definition of what the Simcard Index shows, shared by the page and
     * its Excel export so the download can never drift from the grid.
     */
    private function filteredQuery(Request $request)
    {
        $sortKey = $request->sortKey ? $request->sortKey : 'created_at';
        $sortBy = filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN);

        return Simcard::query()
            ->when($request->code, function ($query, $search) {
                $query->where('code', 'LIKE', "%{$search}%");
            })
            ->when($request->vend_code, function ($query, $search) {
                $query->whereHas('vends', function ($query) use ($search) {
                    $query->where('code', 'LIKE', "%{$search}%");
                });
            })
            // Site — the same free-text rule every other Site box in mark1 uses
            // (Site ID / name / prefix-code), matched against the Site any bound
            // machine sits at. Customer's own operator scope still applies, so a
            // viewer cannot probe for Sites they may not see.
            ->when($request->customer, function ($query, $search) {
                $query->whereHas('vends.customer', function ($query) use ($search) {
                    SiteSearch::for($search)->applyTo($query);
                });
            })
            ->when($request->telco_id, function ($query, $search) {
                $query->where('telco_id', $search);
            })
            // Status — the latest telco-API snapshot (simcards.usage_status).
            // Sims on a telco with no usage API are null and match neither value.
            ->when($request->usage_status, function ($query, $search) {
                $query->where('usage_status', $search);
            })
            ->orderBy($this->sortExpression($sortKey), $sortBy ? 'asc' : 'desc');
    }

    /**
     * What a sort key orders by. The four machine-derived columns (Machine ID /
     * Site / Machine APK / Signal Strength) have no column of their own on
     * simcards, so each is a correlated subquery over the FIRST bound machine —
     * the same machine whose line sits at the top of the on-screen stack. Vend's
     * operator scope rides along, so a restricted viewer never sorts on a
     * machine they cannot see.
     *
     * Anything unrecognised falls back to created_at rather than reaching a raw
     * request string into ORDER BY.
     */
    private function sortExpression(string $sortKey)
    {
        $firstVend = fn () => Vend::query()
            ->whereColumn('vends.simcard_id', 'simcards.id')
            ->orderBy('vends.id')
            ->limit(1);

        return match ($sortKey) {
            'vend_code' => $firstVend()->select('vends.code'),

            'site' => $firstVend()
                ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
                ->select('customers.name'),

            // Vend::reportedApkVersion() in SQL: the higher of the OTA check-in
            // column and the PWRON frame's apk_ver_json.apkver.
            'apk_version' => $firstVend()->select(DB::raw(
                'GREATEST(COALESCE(vends.apk_version_code, 0), '
                .'COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(vends.apk_ver_json, "$.apkver")) AS UNSIGNED), 0))'
            )),

            // internetLink.js signalLevel(): the device declares its own scale,
            // so normalise to the 5-bar scale the pill shows. Only machines that
            // have reported a link count, matching what the column renders.
            'signal' => $firstVend()
                ->whereNotNull('vends.internet_source')
                ->select(DB::raw(
                    'ROUND(vends.internet_signal / COALESCE(NULLIF(vends.internet_signal_max, 0), 5) * 5)'
                )),

            'code', 'telco_id', 'usage_status', 'updated_at', 'created_at' => $sortKey,

            default => 'created_at',
        };
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required',
            'telco_id' => 'required',
        ]);

        $simcard = Simcard::create(array_merge($request->except('vend_id'), [
            'created_by' => auth()->id(),
        ]));

        if ($request->vend_id) {
            \App\Models\Vend::where('id', $request->vend_id)->update(['simcard_id' => $simcard->id]);
        }

        return redirect()->route('simcards');
    }

    public function update(Request $request, $zoneId)
    {
        $request->validate([
            'code' => 'required',
            'telco_id' => 'required',
        ]);

        $simcard = Simcard::findOrFail($zoneId);
        $simcard->update($request->except('vend_id'));

        if ($request->has('vend_id')) {
            \App\Models\Vend::where('simcard_id', $simcard->id)->update(['simcard_id' => null]);
            if ($request->vend_id) {
                \App\Models\Vend::where('id', $request->vend_id)->update(['simcard_id' => $simcard->id]);
            }
        }

        // Stamp who touched it and when — explicitly, so the "Updated By" column
        // moves even when only the vend binding changed (that write lands on
        // vends, not this row, and an all-same-values update() is a no-op).
        $simcard->forceFill([
            'updated_by' => auth()->id(),
            'updated_at' => now(),
        ])->save();

        return redirect()->route('simcards');
    }

    public function delete($zoneId)
    {
        $simcard = Simcard::findOrFail($zoneId);
        $simcard->delete();

        return redirect()->route('simcards');
    }
}
