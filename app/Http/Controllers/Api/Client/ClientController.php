<?php

namespace App\Http\Controllers\Api\Client;

use App\Models\Vend;
use App\Models\VendTransaction;
use App\Http\Controllers\Controller;
// use App\Http\Requests\ClientVendTransactionRequest;
use App\Http\Resources\V1\Client\ClientVendResource;
use App\Http\Resources\V1\Client\ClientVendTransactionResource;
use Carbon\Carbon;
use Illuminate\Http\Request;


class ClientController extends Controller
{
    public function getTransactions(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Enforce max 12 months range
        $dateFrom = Carbon::parse($request->date_from);
        $dateTo = Carbon::parse($request->date_to);

        if ($dateFrom->diffInDays($dateTo) > 366) { // Allow up to 366 days
            return response()->json(['message' => 'Date range cannot exceed 12 months.'], 422);
        }

        // Restrict filters to only allowed keys
        $allowedFilters = ['codes', 'order_id', 'date_from', 'date_to', 'per_page', 'page'];
        $cleanRequest = new Request($request->only($allowedFilters));

        $perPage = $request->input('per_page', 50);

        $vendTransactions = VendTransaction::with([
            'paymentMethod',
            'product',
            'vend.customer',
            'vendChannel',
            'vendChannelError',
        ])
            ->filterTransactionIndex($cleanRequest)
            ->paginate($perPage);

        return ClientVendTransactionResource::collection($vendTransactions);
    }

    public function getChannels(Request $request)
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Restrict filters to only allowed keys
        $allowedFilters = ['codes', 'per_page', 'page'];
        $cleanRequest = new Request($request->only($allowedFilters));

        $perPage = $request->input('per_page', 50);

        $vendChannels = Vend::with([
            'customer',
            'vendChannels.product',
        ])
            ->where('is_active', true)
            ->filterIndex($cleanRequest)
            ->paginate($perPage);

        return ClientVendResource::collection($vendChannels);
    }
}
