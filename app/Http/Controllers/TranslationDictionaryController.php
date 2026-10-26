<?php

namespace App\Http\Controllers;

use App\Services\I18n\TranslationCatalog;
use Illuminate\Http\Request;

/**
 * One language's whole dictionary for the Vue pages (TranslationCatalog::dictionary). Asked for with the
 * `?v=` the page was given, so the browser keeps it until the wording changes.
 */
class TranslationDictionaryController extends Controller
{
    public function show(Request $request, string $locale, TranslationCatalog $catalog)
    {
        abort_unless(array_key_exists($locale, TranslationCatalog::LOCALES), 404);

        $current = $request->query('v') === $catalog->version($locale);

        return response()->json((object) $catalog->dictionary($locale))
            ->header('Cache-Control', $current ? 'private, max-age=31536000, immutable' : 'no-cache');
    }
}
