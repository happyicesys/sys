<?php

namespace App\Http\Controllers;

use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('PaymentMethod/Index', [
            'paymentMethods' => PaymentMethodResource::collection(
                PaymentMethod::query()
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            ),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        PaymentMethod::create($request->all());

        return redirect()->route('payment-methods');
    }

    public function update(Request $request, $paymentMethodId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $paymentMethod = PaymentMethod::findOrFail($paymentMethodId);
        $paymentMethod->update($request->all());

        return redirect()->route('payment-methods');
    }

    public function delete($paymentMethodId)
    {
        $paymentMethod = PaymentMethod::findOrFail($paymentMethodId);
        $paymentMethod->delete();

        return redirect()->route('payment-methods');
    }
}
