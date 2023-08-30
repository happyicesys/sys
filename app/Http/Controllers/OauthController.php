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
        $clients = Client::with('tokens')->get();
        // $personalAccessTokens = Token::all();

        return Inertia::render('Oauth/Index', [
            'clients' => $clients,
            // 'personal_access_tokens' => $personalAccessTokens,
        ]);
    }
}
