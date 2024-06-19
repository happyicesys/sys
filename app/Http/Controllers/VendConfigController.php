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
            'numberPerPage' => $request->numberPerPage ? $request->numberPerPage : 100,
            'sortKey' => $request->sortKey ? $request->sortKey : 'name',
            'sortBy' => $request->sortBy ? $request->sortBy : true,
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
                        'vendPrefixes'
                    ])
                    ->when($request->is_active, function($query, $search) {
                        // dd(filter_var($search, FILTER_VALIDATE_BOOLEAN));
                        $query->where('is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
                    })
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->sortKey, function($query, $search) use ($request) {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($request->numberPerPage === 'All' ? 10000 : $request->numberPerPage)
                    ->withQueryString()
            ),
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
            'vendPrefixes',
        ])
        ->findOrFail($id);

        return Inertia::render('VendConfig/Edit', [
            'vendConfig' => VendConfigResource::make($model),
            'vendPrefixOptions' => VendPrefixResource::collection(
                VendPrefix::query()
                    ->orderBy('name')
                    ->get()
            ),
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

        $model->save();

        return redirect()->route('vend-configs.edit', [$id]);
    }

    public function delete($id)
    {
        $model = VendConfig::findOrFail($id);
        $model->delete();

        return redirect()->route('vend-configs');
    }

    public function uploadAttachment(Request $request, $id)
    {
        $customer = VendConfig::findOrFail($id);

        if ($request->hasFile('files')) {
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
