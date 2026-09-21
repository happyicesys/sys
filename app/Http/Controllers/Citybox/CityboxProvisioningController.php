<?php

namespace App\Http\Controllers\Citybox;

use App\Exceptions\CityboxApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Citybox\ProvisionChillerVendRequest;
use App\Services\Citybox\DeviceProvisioningService;
use App\Services\Citybox\DTO\ChillerDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Endpoints behind the Create-page "Smart Chiller — CityBox" branch (§8c.2). Thin. */
class CityboxProvisioningController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:create machine-settings');
    }

    /** Unlinked devices for the dropdown (+ which ids are already linked, and to what). */
    public function devices(Request $request, DeviceProvisioningService $svc): JsonResponse
    {
        if (! config('citybox.openapi.enabled')) {
            return response()->json(['enabled' => false, 'unlinked' => [], 'linked' => []]);
        }
        try {
            $r = $svc->devices(fresh: (bool) $request->boolean('fresh'));
        } catch (CityboxApiException $e) {
            return response()->json(['enabled' => true, 'error' => $e->getMessage(), 'unlinked' => [], 'linked' => []], 200);
        }

        return response()->json([
            'enabled' => true,
            'unlinked' => $r['unlinked']->map(fn (ChillerDevice $d) => $this->deviceRow($d))->values(),
            'linked' => $r['linked'],
        ]);
    }

    /** Preview card for one chosen device. */
    public function preview(string $equipmentId, DeviceProvisioningService $svc): JsonResponse
    {
        $p = $svc->preview($equipmentId);

        return response()->json([
            'device' => $p['device'] ? $this->deviceRow($p['device']) : null,
            'machine_id' => $p['machine_id'],
            'machine_id_error' => $p['machine_id_error'],
            'state' => $p['state'],
            'product_count' => $p['product_count'],
        ]);
    }

    public function store(ProvisionChillerVendRequest $request, DeviceProvisioningService $svc): RedirectResponse
    {
        $device = $svc->device($request->equipment_id);
        if (! $device) {
            return redirect()->back()->withErrors(['equipment_id' => 'CityBox no longer lists this device — refresh the list.'])->withInput();
        }

        try {
            $vend = $svc->provision($device, $request->only(['name', 'begin_date', 'customer_id']), $request->user());
        } catch (CityboxApiException $e) {
            return redirect()->back()->withErrors(['equipment_id' => $e->getMessage()])->withInput();
        }

        $label = $vend->codeLabel();
        $message = $vend->customer
            ? sprintf('Smart Chiller %s imported from %s and bound to "%s". First stock sync in ≤3 min, or press Pull.', $label, $device->equipmentId, $vend->customer->name)
            : sprintf('Smart Chiller %s imported from %s with no site. Create the site in ConnectVend, then bind it in the Site section below — it cannot join an ops job until then.', $label, $device->equipmentId);

        return redirect()->route('settings.edit', [$vend->id])->with('success', $message);
    }

    /** Sites under the Citybox operator, for the "bind to another existing site" picker. */
    public function customerSearch(Request $request, DeviceProvisioningService $svc): JsonResponse
    {
        $q = trim((string) $request->q);
        $rows = \App\Models\Customer::withoutGlobalScopes()
            ->where('operator_id', $svc->operator()->id)
            ->when($q !== '', fn ($qq) => $qq->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")))
            ->orderBy('name')->limit(20)->get(['id', 'code', 'name']);

        return response()->json($rows->map(fn ($c) => ['id' => $c->id, 'code' => $c->code, 'name' => $c->name]));
    }

    private function deviceRow(ChillerDevice $d): array
    {
        return [
            'equipment_id' => $d->equipmentId,
            'name' => $d->name,
            'type' => $d->type->value,
            'model' => $d->type->modelName(),
            'online' => $d->online,
            'ops_status' => $d->opsStatus?->label(),
            'offline_since' => $d->online ? null : $d->heartbeatOffline?->toDateTimeString(),
        ];
    }
}
