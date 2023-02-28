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
        ]);
        $vendTransactions = ClientVendTransactionResource::collection(
            VendTransaction::with([
                'paymentMethod',
                'product',
                'vend.latestVendBinding.customer',
                'vendChannel',
                'vendChannelError',
            ])
            ->filterTransactionIndex($request)
            ->get()
        );
        return $vendTransactions;
    }

    public function getChannels(Request $request)
    {
        $vendChannels = ClientVendResource::collection(
            Vend::with([
                'latestVendBinding.customer',
                'vendChannels.product',
            ])
            ->filterIndex($request)
            ->get()
        );
        return $vendChannels;
    }
}
