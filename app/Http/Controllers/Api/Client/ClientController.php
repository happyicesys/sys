<?php

namespace App\Http\Controllers\Api\Client;

use App\Models\Vend;
use App\Models\VendTransaction;
use App\Http\Controllers\Controller;
// use App\Http\Requests\ClientVendTransactionRequest;
use App\Http\Resources\ClientVendResource;
use App\Http\Resources\ClientVendTransactionResource;
use Illuminate\Http\Request;


class ClientController extends Controller
{
    public function getTransactions(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $request->input('per_page', 50);

        $vendTransactions = VendTransaction::with([
            'paymentMethod',
            'product',
            'vend.customer',
            'vendChannel',
            'vendChannelError',
        ])
            ->filterTransactionIndex($request)
            ->paginate($perPage);

        return ClientVendTransactionResource::collection($vendTransactions);
    }

    public function getChannels(Request $request)
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $request->input('per_page', 50);

        $vendChannels = Vend::with([
            'customer',
            'vendChannels.product',
        ])
            ->filterIndex($request)
            ->paginate($perPage);

        return ClientVendResource::collection($vendChannels);
    }
}
