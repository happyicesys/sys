<?php

namespace App\Http\Middleware;

use App\Services\I18n\TranslationCatalog;
use Closure;
use Illuminate\Http\Request;

/** Server messages (`__()`, validation) in the signed-in user's language; English otherwise. */
class SetUserLocale
{
    public function handle(Request $request, Closure $next)
    {
        app()->setLocale(TranslationCatalog::normalize($request->user()?->locale));

        return $next($request);
    }
}
