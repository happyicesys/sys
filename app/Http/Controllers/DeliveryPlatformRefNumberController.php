<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeliveryPlatformRefNumberController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'created_at';
        $sortBy = $request->sortBy ? $request->sortBy : false;

        return Inertia::render('DeliveryPlatformRefNumber/Index', [
            'deliveryPlatformRefNumbers' => DeliveryPlatformRefNumberResource::collection(
                DeliveryPlatformRefNumber::with('operator')
                    ->when($request->name, function($query, $search) {
                        $query->where('ref_number', 'LIKE', "%{$search}%");
                    })
                    ->when($request->operators, function($query, $search) {
                        if(!in_array('all', $search)){
                            $query->whereIn('operator_id', $search);
                        }
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
            'operatorOptions' => OperatorResource::collection(
                Operator::orderBy('name')->get()
            ),
        ]);
    }
}
