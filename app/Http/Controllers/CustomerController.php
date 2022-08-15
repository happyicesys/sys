<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerResource;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Customer/Index', [
            'customers' => CustomerResource::collection(
                Customer::query()
                                ->when($request->code, function($query, $search) {
                                    $query->where('code', 'LIKE', "%{$search}%");
                                })
                                ->when($request->name, function($query, $search) {
                                    $query->where('name', 'LIKE', "%{$search}%");
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
}
