<?php

namespace App\Http\Controllers;

use App\Http\Resources\VendingMachineResource;
use App\Http\Resources\VendingMachineTempResource;
use App\Models\VendingMachine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;


class VendingMachineController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('VendingMachine/Index', [
            'vendingMachines' => VendingMachineResource::collection(
                VendingMachine::query()
                                ->when($request->code, function($query, $search) {
                                    $query->where('code', 'LIKE', "%{$search}%");
                                })
                                ->when($request->serial_num, function($query, $search) {
                                    $query->where('serial_num', 'LIKE', "%{$search}%");
                                })
                                ->when($request->name, function($query, $search) {
                                    $query->where('name', 'LIKE', "%{$search}%");
                                })
                                ->orderBy('code')
                                ->paginate(50)
                                ->withQueryString()
                            ),
            'filters' => $request->only(['code', 'serial_num', 'name']),
        ]);
    }

    public function temp($vendingMachineId)
    {
        $vendingMachine = VendingMachine::findOrFail($vendingMachineId);

        return Inertia::render('VendingMachine/Temp', [
            'vendingMachine' => new VendingMachineResource($vendingMachine),
            'vendingMachineTemps' => VendingMachineTempResource::collection(
                $vendingMachine->vendingMachineTemps()->whereDate('created_at', '>=', Carbon::now()->subDays(7))->get()
            ),
        ]);
    }
}
