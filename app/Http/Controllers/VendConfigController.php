<?php

namespace App\Http\Controllers;

use App\Http\Resources\OperatorResource;
use App\Http\Resources\VendConfigResource;
use App\Http\Resources\VendPrefixResource;
use App\Models\Operator;
use App\Models\VendConfig;
use App\Models\VendPrefix;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class VendConfigController extends Controller
{
    public function index(Request $request)
    {
        // dd($request->all());
        $request->merge([
            'is_active' => $request->is_active ? $request->is_active : 'true',
            'vendStatus' => $request->vendStatus ? $request->vendStatus : 'active',
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'name',
            'sortBy' => $request->sortBy ? $request->sortBy : false,
        ]);

        // dd($request->all());
        return Inertia::render('VendConfig/Index', [
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
            'vendConfigs' => VendConfigResource::collection(
                VendConfig::query()
                    ->with([
                        'attachments',
                        'vendConfigCompatibles',
                        'vendPrefixes'
                    ])
                    ->withCount([
                        'vends' => function ($query) use ($request) {
                            if ($request->vendStatus and $request->vendStatus !== 'all') {
                                switch ($request->vendStatus) {
                                    case 'active':
                                        $query->where('is_active', true);
                                        break;
                                    case 'inactive':
                                        $query->where('is_active', false);
                                        break;
                                    case 'factory':
                                        $query->where('is_testing', true);
                                        break;
                                    case 'disposed':
                                        $query->where('is_disposed', true);
                                        break;
                                }
                            }
                        },
                    ])
                    ->when($request->is_active, function($query, $search) {
                        // dd(filter_var($search, FILTER_VALIDATE_BOOLEAN));
                        $query->where('is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
                    })
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->vendPrefixes, function($query, $search) {
                        $query->whereHas('vendPrefixes', function($query) use ($search) {
                            if(in_array('single-ud', $search)) {
                                $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                                unset($search[array_search('single-ud', $search)]);
                            }
                            $query->whereIn('vend_prefix_id', $search);
                        });
                    })
                    ->when($request->vendStatus, function($query, $search) {
                        if($search != 'all') {
                            $query->whereHas('vends', function($query) use ($search) {
                                switch($search) {
                                    case 'disposed':
                                        $query->where('is_disposed', true);
                                        break;
                                    case 'factory':
                                        $query->where('is_testing', true);
                                        break;
                                    case 'active':
                                        $query->where('is_active', true);
                                        break;
                                    case 'inactive':
                                        $query->where('is_active', false);
                                        break;
                                }
                            });
                        }
                    })
                    ->when($request->version, function($query, $search) {
                        if($search !== 'all') {
                            $query->where('version', $search);
                        }
                    })
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::query()
                    ->orderBy('name')
                    ->get()
            ),
            'versionOptions' => VendConfig::VERSION,
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $vendConfig = VendConfig::create($request->all());

        return redirect()->route('vend-configs.edit', [$vendConfig->id]);
    }

    public function edit($id)
    {
        $model = VendConfig::with([
            'attachments',
            'operator',
            'vendConfigCompatibles',
            'vendConfigCompatibleWith',
            'vendPrefixes',
        ])
        ->findOrFail($id);

        return Inertia::render('VendConfig/Edit', [
            'vendConfig' => VendConfigResource::make($model),
            'vendConfigOptions' => VendConfigResource::collection(
                VendConfig::query()
                    ->where('id', '!=', $id)
                    ->orderBy('name')
                    ->get()
            ),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::query()
                    ->orderBy('name')
                    ->get()
            ),
            'versionOptions' => VendConfig::VERSION,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $model = VendConfig::findOrFail($id);
        $model->fill($request->all());

        // if($request->vendPrefixes) {
        $model->vendPrefixes()->sync(collect($request->vendPrefixes)->pluck('id'));
        // }
        $model->syncCompatibles($request->vendConfigCompatibles);

        $model->save();

        return redirect()->route('vend-configs.edit', [$id]);
    }

    public function delete($id)
    {
        $model = VendConfig::findOrFail($id);

        if (!$model->operator_id) {
            return redirect()->route('vend-configs')->withErrors([
                'delete' => 'Global Setting Charts cannot be deleted.',
            ]);
        }

        $model->delete();

        return redirect()->route('vend-configs');
    }

    public function toggleActivateDeactivate($vendConfigID)
    {
        $vendConfig = VendConfig::findOrFail($vendConfigID);
        $vendConfig->is_active = !$vendConfig->is_active;
        $vendConfig->save();

        return redirect()->route('vend-configs');
    }

    public function uploadAttachment(Request $request, $id)
    {
        $customer = VendConfig::findOrFail($id);

        if ($request->files) {
            $files = $request->file('files');
            $dir = 'sys/vend-configs';
            $storedPath = $files->storePublicly($dir);
            $fileName = basename($storedPath);
            $url = Storage::url($storedPath);
            $customer->attachments()->create([
                'type' => 1,
                'full_url' => $url,
                'local_url' => $dir . '/' . $fileName,
            ]);
        }
        return true;
    }
}
