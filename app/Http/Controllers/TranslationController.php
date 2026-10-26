<?php

namespace App\Http\Controllers;

use App\Models\UiTranslation;
use App\Services\I18n\TranslationCatalog;
use App\Services\I18n\TranslationPlaces;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Settings → Translations (Brian, 2026-10-26): find any text of the UI and change its wording in
 * either language without a deploy. The list is every text the code uses — the keys of
 * `lang/zh_CN.json`, which `i18n:scan --sync` keeps complete — plus any text someone already changed.
 */
class TranslationController extends Controller
{
    private const MASTER_LOCALE = 'zh_CN';

    private const PER_PAGE = 50;

    public function index(Request $request, TranslationCatalog $catalog, TranslationPlaces $places)
    {
        $locale = TranslationCatalog::normalize($request->query('locale', self::MASTER_LOCALE));
        $search = trim((string) $request->query('search', ''));
        $status = in_array($request->query('status'), ['missing', 'changed'], true) ? $request->query('status') : 'all';
        $page = trim((string) $request->query('page_name', ''));
        $where = $places->all();
        $pages = $places->pages();
        $label = fn (string $id) => $pages[$id]['label'] ?? $id;

        $master = $this->masterKeys($catalog);
        $shipped = $locale === TranslationCatalog::DEFAULT_LOCALE ? [] : $catalog->base($locale);
        $changed = UiTranslation::with('updatedBy:id,name')->where('locale', $locale)->get()->keyBy('key');
        $keys = array_values(array_unique(array_merge($master, $changed->keys()->all())));

        $rows = collect($keys)->map(function (string $key) use ($locale, $shipped, $changed, $where, $label) {
            $row = $changed->get($key);

            return [
                'key' => $key,
                'shipped' => $locale === TranslationCatalog::DEFAULT_LOCALE ? $key : ($shipped[$key] ?? ''),
                'value' => $row?->value ?? '',
                'updated_by' => $row?->updatedBy?->name,
                'updated_at' => $row?->updated_at?->toDateTimeString(),
                // Where the text is shown; one wording serves every place listed.
                'place_ids' => $where[$key] ?? [],
                'places' => array_map($label, $where[$key] ?? []),
            ];
        });
        if ($page !== '') {
            $rows = $rows->filter(fn ($r) => in_array($page, $r['place_ids'], true));
        }
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['key']), $needle)
                || str_contains(mb_strtolower($r['shipped']), $needle)
                || str_contains(mb_strtolower($r['value']), $needle));
        }
        if ($status === 'missing') {
            $rows = $rows->filter(fn ($r) => $r['shipped'] === '' && $r['value'] === '');
        } elseif ($status === 'changed') {
            $rows = $rows->filter(fn ($r) => $r['value'] !== '');
        }
        $rows = $rows->sortBy(fn ($r) => mb_strtolower($r['key']))->values();

        $pageNumber = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator(
            $rows->forPage($pageNumber, self::PER_PAGE)->values(), $rows->count(), self::PER_PAGE, $pageNumber,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Translation/Index', [
            'entries' => $paginator,
            'locales' => TranslationCatalog::LOCALES,
            'filters' => ['locale' => $locale, 'search' => $search, 'status' => $status, 'page_name' => $page],
            // Place id => readable name (menu section › entry › page title · address) and number of texts.
            'pages' => $pages,
            'counts' => [
                'total' => count($master),
                'translated' => count(array_intersect_key($catalog->base(self::MASTER_LOCALE), array_flip($master))),
                'changed' => $changed->count(),
            ],
        ]);
    }

    /** Where one text is shown — for the Edit wording box. */
    public function places(Request $request, TranslationPlaces $places)
    {
        $key = (string) $request->query('key', '');

        return response()->json(['key' => $key, 'places' => $places->for($key)]);
    }

    public function update(Request $request, TranslationCatalog $catalog)
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(array_keys(TranslationCatalog::LOCALES))],
            'key' => ['required', 'string', 'max:5000'],
            'value' => ['nullable', 'string', 'max:5000'],
        ]);

        $catalog->setOverride($data['locale'], $data['key'], $data['value'] ?? null, $request->user());

        // The in-page editor (wording edit mode) saves over JSON and shows the result at once.
        if ($request->wantsJson()) {
            return response()->json([
                'key' => $data['key'],
                'value' => $catalog->dictionary($data['locale'])[$data['key']] ?? null,
                'version' => $catalog->version($data['locale']),
            ]);
        }

        return back();
    }

    /** @return string[] */
    private function masterKeys(TranslationCatalog $catalog): array
    {
        $path = $catalog->path(self::MASTER_LOCALE);
        $json = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];

        return array_keys(is_array($json) ? $json : []);
    }
}
