<?php

namespace App\Http\Controllers;

use App\Services\I18n\TranslationCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The top-right language switch: each user's own choice, kept on their account (any device). */
class LocaleController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate(['locale' => ['required', Rule::in(array_keys(TranslationCatalog::LOCALES))]]);

        $locale = $data['locale'] === TranslationCatalog::DEFAULT_LOCALE ? null : $data['locale'];
        $request->user()->forceFill(['locale' => $locale])->save();

        return response()->noContent();
    }
}
