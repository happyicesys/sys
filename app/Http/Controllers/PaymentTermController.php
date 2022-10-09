<?php

namespace App\Http\Controllers;

use App\Http\Resources\PaymentTermResource;
use App\Models\PaymentTerm;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentTermController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('PaymentTerm/Index', [
            'paymentTerms' => PaymentTermResource::collection(
                PaymentTerm::query()
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

        PaymentTerm::create($request->all());

        return redirect()->route('payment-terms');
    }

    public function update(Request $request, $paymentTermId)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $paymentTerm = PaymentTerm::findOrFail($paymentTermId);
        $paymentTerm->update($request->all());

        return redirect()->route('payment-terms');
    }

    public function delete($paymentTermId)
    {
        $paymentTerm = PaymentTerm::findOrFail($paymentTermId);
        $paymentTerm->delete();

        return redirect()->route('payment-terms');
    }
}
