<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorResource;
use App\Http\Resources\VendAlertParameterResource;
use App\Models\Operator;
use App\Models\Vend;
use App\Models\VendAlertSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class VendAlertParameterController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:read vend-settings'])->only('index');
        $this->middleware(['permission:update vend-settings'])->only('bulkUpdate');
    }

    public function index(Request $request)
    {
        $filters = [
            'code' => $request->input('code'),
            'name' => $request->input('name'),
            'operators' => $request->input('operators', 'all'),
            'numberPerPage' => $request->input('numberPerPage', 100),
        ];

        $query = Vend::query()
            ->with([
                'operator:id,name,code',
                'customer:id,name,code',
                'alertSetting',
            ])
            ->select('vends.*')
            ->when($filters['code'], function ($query, $code) {
                $query->where('vends.code', 'like', '%' . $code . '%');
            })
            ->when($filters['name'], function ($query, $name) {
                $query->where('vends.name', 'like', '%' . $name . '%');
            })
            ->when($filters['operators'], function ($query) use ($filters) {
                if ($filters['operators'] === 'all') {
                    return;
                }

                $operatorIds = array_filter(
                    Arr::wrap($filters['operators']),
                    fn ($id) => $id !== 'all' && $id !== null && $id !== ''
                );

                if (!empty($operatorIds)) {
                    $query->whereIn('vends.operator_id', $operatorIds);
                }
            })
            ->orderBy('vends.code');

        $perPage = $filters['numberPerPage'];
        $paginator = $query
            ->paginate($perPage === 'All' ? 10000 : $perPage)
            ->withQueryString();

        return Inertia::render('MachineAlertParameter/Index', [
            'vends' => VendAlertParameterResource::collection($paginator),
            'filters' => $filters,
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'vend_ids' => ['required', 'array', 'min:1'],
            'vend_ids.*' => ['integer', 'exists:vends,id'],
            'offline_after_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'power_restored_after_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'no_sales_after_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
        ]);

        $fieldsToUpdate = [];
        foreach (['offline_after_minutes', 'power_restored_after_minutes', 'no_sales_after_hours'] as $field) {
            if ($request->exists($field)) {
                $fieldsToUpdate[$field] = $validated[$field] ?? null;
            }
        }

        if (empty($fieldsToUpdate)) {
            return Redirect::back()->with('warning', 'No alert parameter changes provided.');
        }

        $affected = 0;

        DB::transaction(function () use (&$affected, $validated, $fieldsToUpdate) {
            foreach ($validated['vend_ids'] as $vendId) {
                /** @var \App\Models\VendAlertSetting $setting */
                $setting = VendAlertSetting::firstOrNew(['vend_id' => $vendId]);

                foreach ($fieldsToUpdate as $field => $value) {
                    $setting->{$field} = $value;
                }

                if ($this->settingIsEmpty($setting)) {
                    if ($setting->exists) {
                        $setting->delete();
                        $affected++;
                    }
                    continue;
                }

                if (!$setting->exists || $setting->isDirty(array_keys($fieldsToUpdate))) {
                    $setting->save();
                    $affected++;
                }
            }
        });

        if ($affected === 0) {
            return Redirect::back()->with('info', 'Selected machines already had the requested alert parameters.');
        }

        return Redirect::back()->with('success', "Alert parameters updated for {$affected} machine(s).");
    }

    protected function settingIsEmpty(VendAlertSetting $setting): bool
    {
        return collect([
            $setting->offline_after_minutes,
            $setting->power_restored_after_minutes,
            $setting->no_sales_after_hours,
        ])->every(function ($value) {
            return $value === null;
        });
    }
}
