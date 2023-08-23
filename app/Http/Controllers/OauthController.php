<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Passport\Client;
use Laravel\Passport\Token;

class OauthController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::all();
        $authorizedClients = Client::whereIn('id', Token::whereNotNull('expires_at')->pluck('client_id'))->get();
        $personalAccessTokens = Token::query()
                    ->where('client_id', null) // Personal access tokens have no client
                    ->get();

        dd($clients->toArray(), $authorizedClients->toArray(), $personalAccessTokens->toArray());

        return Inertia::render('Oauth/Index', [
            'clients' => $clients,
            'apps' => $authorizedClients,
            'personal_access_tokens' => $personalAccessTokens,
        ]);
    }
}
