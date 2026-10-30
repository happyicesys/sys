<?php

namespace App\Services\I18n;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Where each UI text is shown (Brian, 2026-10-26), named the way a person finds it — never a file name
 * ("for the layman … use the URL and which section"): the menu section and entry, the page title and its
 * address, e.g. "Machine Management › Machines Settings › VM Edit · /settings/vend/{id}/update".
 *
 *  - A page is linked to its address by the GET route whose controller renders it (Inertia::render).
 *  - Its menu section is the menu entry (Layouts/Authenticated.vue) whose link is the longest start of
 *    that address.
 *  - A form, pop-up or part a page imports counts as that page, so the Page filter shows its texts too.
 *    A piece used on more than SHARED_AFTER pages is "Shared" instead of listing them all.
 *  - A server message names the area of its controller's addresses.
 *
 * Built from the code (TextScanner::keys(), the routes, the imports), cached until the next deploy;
 * names are put together per request so they follow the reader's language.
 */
class TranslationPlaces
{
    private const SHARED_AFTER = 6;

    /** Server classes whose messages are shown on one known page, not through their own controller. */
    private const SERVER_AREAS = [
        'CriticalAttention' => '/vends/customers',
        'MachineAlerts' => '/vends/customers',
        'TranslationPlaces' => '/translations',
    ];

    /** @return array<string, string[]> key => place ids */
    public function all(): array
    {
        return $this->data()['keys'];
    }

    /** @return string[] readable names of the places one text is shown */
    public function for(string $key): array
    {
        return array_map(fn ($id) => $this->label($id), $this->all()[$key] ?? []);
    }

    /** @return array<string, array{label: string, count: int}> place id => name and number of texts, by name */
    public function pages(): array
    {
        $count = [];
        foreach ($this->all() as $ids) {
            foreach ($ids as $id) {
                $count[$id] = ($count[$id] ?? 0) + 1;
            }
        }
        $out = [];
        foreach ($count as $id => $n) {
            $out[$id] = ['label' => $this->label($id), 'count' => $n];
        }
        uasort($out, fn ($a, $b) => strnatcasecmp($a['label'], $b['label']));

        return $out;
    }

    /** One place, named in the reader's language. */
    public function label(string $id): string
    {
        $place = $this->data()['places'][$id] ?? null;
        if ($place === null) {
            return $id;
        }

        return match ($place['kind']) {
            'layout' => __('Menu & top bar (every page)'),
            'page' => $this->pageLabel($place),
            'shared' => __('Shared — used on :count pages (e.g. :page)', [
                'count' => $place['count'], 'page' => $this->pageLabel($this->data()['places'][$place['example']] ?? []),
            ]),
            'server' => $place['area']
                ? __('System message shown in :area', ['area' => $this->pageLabel($place['area'])])
                : __('System message'),
            default => $id,
        };
    }

    private function pageLabel(array $place): string
    {
        $parts = array_map(fn ($p) => __($p), array_filter($place['menu'] ?? []));
        $title = $place['title'] ?? null;
        if ($title !== null && ! in_array($title, $place['menu'] ?? [], true)) {
            $parts[] = __($title);
        }
        $name = implode(' › ', $parts);
        if ($name === '' && empty($place['url'])) {
            return __('Other screen (not opened from the menu)');
        }

        if (empty($place['url'])) {
            return $name;
        }

        return $name === '' ? $place['url'] : $name.' · '.$place['url'];
    }

    /** @return array{keys: array<string, string[]>, places: array<string, array>} */
    private function data(): array
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }
        $manifest = public_path('build/manifest.json');
        $stamp = is_file($manifest) ? filemtime($manifest).'-'.filesize($manifest) : 'dev';

        return $memo = Cache::remember('i18n:places:v5:'.$stamp, now()->addDays(30), fn () => $this->build());
    }

    private function build(): array
    {
        $menu = $this->menu();
        [$pageUrls, $controllerUrls] = $this->routes();
        $parents = $this->importers();

        $places = ['layout' => ['kind' => 'layout']];
        $pagePlace = function (string $component) use (&$places, $pageUrls, $menu): string {
            $id = 'page:'.$component;
            if (! isset($places[$id])) {
                $url = $pageUrls[$component] ?? null;
                $places[$id] = [
                    'kind' => 'page',
                    'url' => $url,
                    'menu' => $url ? $this->menuFor($url, $menu) : [],
                    'title' => $this->pageTitle('resources/js/Pages/'.$component.'.vue'),
                ];
            }

            return $id;
        };

        $fileIds = [];
        $resolve = function (string $file) use (&$fileIds, &$places, $pageUrls, $parents, $pagePlace, $controllerUrls, $menu): array {
            if (isset($fileIds[$file])) {
                return $fileIds[$file];
            }
            if (str_starts_with($file, 'resources/js/Layouts/')) {
                return $fileIds[$file] = ['layout'];
            }
            if (str_starts_with($file, 'app/')) {
                $class = basename($file, '.php');
                $id = 'server:'.$class;
                $url = self::SERVER_AREAS[$class] ?? $controllerUrls[$class] ?? $this->callerUrl($class, $controllerUrls);
                $places[$id] ??= ['kind' => 'server', 'area' => $url ? ['url' => $url, 'menu' => $this->menuFor($url, $menu)] : null];

                return $fileIds[$file] = [$id];
            }
            $component = str_starts_with($file, 'resources/js/Pages/') ? substr($file, strlen('resources/js/Pages/'), -4) : null;
            if ($component !== null && isset($pageUrls[$component])) {
                return $fileIds[$file] = [$pagePlace($component)];
            }
            // A form, pop-up, part or shared piece: the routed pages that import it, up to 4 levels up.
            $pages = [];
            $viaLayout = false;
            $queue = [[$file, 0]];
            $seen = [$file => true];
            while ($queue) {
                [$current, $depth] = array_shift($queue);
                foreach ($parents[$current] ?? [] as $parent) {
                    if (isset($seen[$parent])) {
                        continue;
                    }
                    $seen[$parent] = true;
                    if (str_starts_with($parent, 'resources/js/Layouts/')) {
                        $viaLayout = true;

                        continue;
                    }
                    $parentComponent = str_starts_with($parent, 'resources/js/Pages/') ? substr($parent, strlen('resources/js/Pages/'), -4) : null;
                    if ($parentComponent !== null && isset($pageUrls[$parentComponent])) {
                        $pages[$parentComponent] = true;
                    } elseif ($depth < 4) {
                        $queue[] = [$parent, $depth + 1];
                    }
                }
            }
            $pages = array_keys($pages);
            sort($pages);
            if ($pages === []) {
                return $fileIds[$file] = $viaLayout ? ['layout'] : ($component !== null ? [$pagePlace($component)] : []);
            }
            if (count($pages) > self::SHARED_AFTER) {
                $id = 'shared:'.$file;
                $places[$id] ??= ['kind' => 'shared', 'count' => count($pages), 'example' => $pagePlace($pages[0])];

                return $fileIds[$file] = [$id];
            }

            return $fileIds[$file] = array_map($pagePlace, $pages);
        };

        $keys = [];
        foreach (TextScanner::forApp()->keys() as $key => $files) {
            $ids = [];
            foreach ($files as $file) {
                $ids = array_merge($ids, $resolve($file));
            }
            $keys[$key] = array_values(array_unique($ids));
        }

        return ['keys' => $keys, 'places' => $places];
    }

    /** A service's messages belong to the area of the first controller that uses it. */
    private function callerUrl(string $class, array $controllerUrls): ?string
    {
        foreach ((new \Symfony\Component\Finder\Finder)->files()->in(app_path('Http/Controllers'))->name('*.php')->sortByName() as $file) {
            $controller = $file->getBasename('.php');
            if (isset($controllerUrls[$controller]) && preg_match('/\b'.preg_quote($class, '/').'\b/', $file->getContents())) {
                return $controllerUrls[$controller];
            }
        }

        return null;
    }

    /** @return array<int, array{section: string, item: ?string, href: string}> */
    private function menu(): array
    {
        $source = @file_get_contents(resource_path('js/Layouts/Authenticated.vue')) ?: '';
        $source = preg_replace('/^\s*\/\/.*$/m', '', $source);
        preg_match_all('/^\s{8}name:\s*tk\(\'((?:\\\\\'|[^\'])+)\'\),\s*$/m', $source, $sections, PREG_OFFSET_CAPTURE);
        preg_match_all('/\{\s*name:\s*tk\(\'((?:\\\\\'|[^\'])+)\'\)\s*,\s*href:\s*\'([^\']+)\'(?:\s*,\s*also:\s*\[([^\]]*)\])?/', $source, $items, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        $out = [];
        foreach ($items as $item) {
            $section = null;
            foreach ($sections[1] as [$name, $pos]) {
                if ($pos < $item[0][1]) {
                    $section = $name;
                }
            }
            $name = str_replace("\\'", "'", $item[1][0]);
            $out[] = ['section' => $section, 'item' => $name, 'href' => strtok($item[2][0], '?')];
            // Pages folded into this entry (`also: ['/path', …]`) belong to it too.
            preg_match_all("/'([^']+)'/", $item[3][0] ?? '', $also);
            foreach ($also[1] as $path) {
                $out[] = ['section' => $section, 'item' => $name, 'href' => strtok($path, '?')];
            }
        }

        return $out;
    }

    /** The menu entry whose link is the longest start of the address. */
    private function menuFor(string $url, array $menu): array
    {
        $best = null;
        foreach ($menu as $entry) {
            $href = rtrim($entry['href'], '/');
            if ($href === '' || ($url !== $href && ! str_starts_with($url, $href.'/'))) {
                continue;
            }
            if ($best === null || strlen($href) > strlen($best['href'])) {
                $best = $entry;
            }
        }

        return $best ? array_values(array_filter([$best['section'], $best['item']])) : [];
    }

    /** @return array{0: array<string, string>, 1: array<string, string>} page => address, controller => its shortest address */
    private function routes(): array
    {
        $pages = [];
        $controllers = [];
        $sources = [];
        /** @var Route $route */
        foreach (RouteFacade::getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }
            [$class, $method] = explode('@', $action);
            $short = class_basename($class);
            $uri = '/'.ltrim($route->uri(), '/');
            if (in_array('GET', $route->methods(), true) && (! isset($controllers[$short]) || strlen($uri) < strlen($controllers[$short]))) {
                $controllers[$short] = $uri;
            }
            if (! in_array('GET', $route->methods(), true) || ! method_exists($class, $method)) {
                continue;
            }
            try {
                $reflection = new \ReflectionMethod($class, $method);
            } catch (\ReflectionException) {
                continue;
            }
            $file = $reflection->getFileName();
            $sources[$file] ??= file($file) ?: [];
            $body = implode('', array_slice($sources[$file], $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
            preg_match_all('/(?:Inertia::render|inertia)\(\s*([^,)]+)/', $body, $calls);
            foreach ($calls[1] as $argument) {
                preg_match_all('/[\'"]([A-Z][\w\/]+)[\'"]/', $argument, $names);
                foreach ($names[1] as $component) {
                    if (! isset($pages[$component]) || strlen($uri) < strlen($pages[$component])) {
                        $pages[$component] = $uri;
                    }
                }
            }
        }

        return [$pages, $controllers];
    }

    /** @return array<string, string[]> file => files that import it */
    private function importers(): array
    {
        $parents = [];
        foreach ((new \Symfony\Component\Finder\Finder)->files()->in(resource_path('js'))->name(['*.vue', '*.js']) as $file) {
            $from = 'resources/js/'.str_replace('\\', '/', $file->getRelativePathname());
            preg_match_all('/import\s[^;]*?from\s+[\'"]([^\'"]+)[\'"]|import\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $file->getContents(), $m, PREG_SET_ORDER);
            foreach ($m as $hit) {
                $spec = $hit[1] !== '' ? $hit[1] : ($hit[2] ?? '');
                if (str_starts_with($spec, '@/')) {
                    $target = 'resources/js/'.substr($spec, 2);
                } elseif (str_starts_with($spec, '.')) {
                    $target = $this->normalise(dirname($from).'/'.$spec);
                } else {
                    continue;
                }
                foreach ([$target, $target.'.vue', $target.'.js', $target.'/index.js'] as $candidate) {
                    if (is_file(base_path($candidate))) {
                        $parents[$candidate][] = $from;
                        break;
                    }
                }
            }
        }

        return $parents;
    }

    private function normalise(string $path): string
    {
        $out = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '..') {
                array_pop($out);
            } elseif ($part !== '.' && $part !== '') {
                $out[] = $part;
            }
        }

        return implode('/', $out);
    }

    private function pageTitle(string $file): ?string
    {
        $source = @file_get_contents(base_path($file)) ?: '';
        if (preg_match('/<Head\s+:title="\$t\(\'((?:\\\\.|[^\'])+)\'/', $source, $m)
            || preg_match('/<Head\s+title="([^"]+)"/', $source, $m)) {
            $title = trim(preg_replace('/\s*:\w+\s*$/', '', str_replace("\\'", "'", $m[1])));

            return $title !== '' ? $title : null;
        }

        return null;
    }
}
