<?php

namespace App\Services\I18n;

use Symfony\Component\Finder\Finder;

/**
 * The deep scan behind `php artisan i18n:scan` (Brian, 2026-10-26: "make sure you do a fully deep scan to
 * include all the labels, descriptions, prompt, alert, and also the toast box").
 *
 * Two questions:
 *  - keys(): every text the code already marks for translation — `$t('…')`, `t('…')`, `tc('…')`, `tk('…')`
 *    in resources/js, `__('…')` / `trans('…')` / `trans_choice('…')` in app/ — so the dictionary can be
 *    checked complete.
 *  - hardcoded(): every text a person reads that is NOT marked yet, per file and kind: template text,
 *    text attributes (label, placeholder, title, tooltip, aria-label …), quoted text in bound attributes
 *    and v-tooltip, toast / alert / confirm / prompt calls, UI-ish object fields (name/label/title/text/
 *    message/placeholder/description/hint/tooltip) in scripts, and the PHP messages a page shows
 *    (flash, validation, abort, JSON message). A heuristic — it errs towards listing too much.
 */
class TextScanner
{
    /** Attributes whose static value a person reads. */
    private const TEXT_ATTRIBUTES = ['label', 'placeholder', 'placeholderStr', 'placeholder-str', 'title', 'alt', 'aria-label',
        'header', 'text', 'tooltip', 'description', 'emptyText', 'empty-text', 'confirmText', 'buttonText', 'hint', 'helpText', 'help-text'];

    /** Script object fields that hold a person-readable text. */
    private const TEXT_FIELDS = ['name', 'label', 'title', 'text', 'message', 'placeholder', 'description', 'hint', 'tooltip', 'header', 'value'];

    public function __construct(private readonly string $base) {}

    public static function forApp(): self
    {
        return new self(base_path());
    }

    /** @return array<string, string[]> key => files that use it */
    public function keys(): array
    {
        $keys = [];
        foreach ($this->files(['resources/js'], ['*.vue', '*.js']) as $rel => $source) {
            $source = $this->withoutComments($source);
            $pattern = '/(?<![\w$.])\$?t[ck]?\(\s*([\'"`])((?:\\\\.|(?!\1).)*?)\1/s';
            preg_match_all($pattern, $source, $m, PREG_SET_ORDER);
            foreach ($m as $hit) {
                if ($hit[1] === '`' && str_contains($hit[2], '${')) {
                    continue;
                }
                $keys[$this->unescape($hit[2], $hit[1])][] = $rel;
            }
        }
        foreach ($this->files(['app'], ['*.php']) as $rel => $source) {
            $source = $this->phpWithoutComments($source);
            preg_match_all('/(?<![\w>:$])(?:__|trans|trans_choice)\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1/s', $source, $m, PREG_SET_ORDER);
            foreach ($m as $hit) {
                $key = $this->unescape($hit[2], $hit[1]);
                if (preg_match('/^[a-z0-9_-]+(\.[a-z0-9_-]+)+$/', $key)) {
                    continue; // a group key (validation.required) — lang/{locale}/*.php, not the JSON dictionary
                }
                $keys[$key][] = $rel;
            }
        }
        foreach ($keys as $key => $files) {
            $keys[$key] = array_values(array_unique($files));
        }
        ksort($keys);

        return $keys;
    }

    /**
     * @return array<string, array<int, array{kind: string, line: int, text: string}>> file => findings
     */
    public function hardcoded(?string $only = null): array
    {
        $out = [];
        foreach ($this->files(['resources/js'], ['*.vue', '*.js']) as $rel => $source) {
            if ($only !== null && ! str_contains($rel, $only)) {
                continue;
            }
            $found = str_ends_with($rel, '.vue') ? $this->scanVue($source) : $this->scanScript($source, 0);
            if ($found) {
                $out[$rel] = $found;
            }
        }
        foreach ($this->files(['app/Http', 'app/Services', 'app/Support'], ['*.php']) as $rel => $source) {
            if ($only !== null && ! str_contains($rel, $only)) {
                continue;
            }
            if ($found = $this->scanPhp($this->phpWithoutComments($source))) {
                $out[$rel] = $found;
            }
        }
        uasort($out, fn ($a, $b) => count($b) <=> count($a));

        return $out;
    }

    private function scanVue(string $source): array
    {
        $found = [];
        // Script blocks keep their own line numbers.
        if (preg_match_all('/<script\b[^>]*>(.*?)<\/script>/s', $source, $scripts, PREG_OFFSET_CAPTURE)) {
            foreach ($scripts[1] as [$code, $offset]) {
                $found = array_merge($found, $this->scanScript($code, substr_count(substr($source, 0, $offset), "\n")));
            }
        }
        if (! preg_match('/<template\b[^>]*>(.*)<\/template>/s', $source, $tpl, PREG_OFFSET_CAPTURE)) {
            return $found;
        }
        [$template, $offset] = $tpl[1];
        $lineOf = fn (int $pos) => substr_count(substr($source, 0, $offset + $pos), "\n") + 1;
        // Blank out comments, keeping offsets.
        $template = preg_replace_callback('/<!--.*?-->/s', fn ($c) => preg_replace('/[^\n]/', ' ', $c[0]), $template);

        // Text between tags, minus {{ }} parts.
        preg_match_all('/>([^<>]+)</', $template, $texts, PREG_OFFSET_CAPTURE);
        foreach ($texts[1] as [$text, $pos]) {
            $plain = trim(preg_replace('/\{\{.*?\}\}/s', ' ', $text));
            $plain = trim(html_entity_decode($plain));
            if ($this->isText($plain)) {
                $found[] = ['kind' => 'text', 'line' => $lineOf($pos), 'text' => $this->clip($plain)];
            }
            // Quoted literals inside {{ }} that are not wrapped: {{ x ? 'Yes' : 'No' }}
            preg_match_all('/\{\{(.*?)\}\}/s', $text, $mustaches, PREG_OFFSET_CAPTURE);
            foreach ($mustaches[1] as [$expr, $p]) {
                foreach ($this->bareLiterals($expr) as $literal) {
                    $found[] = ['kind' => 'expression', 'line' => $lineOf($pos + $p), 'text' => $this->clip($literal)];
                }
            }
        }

        // Static text attributes.
        $attrs = implode('|', array_map('preg_quote', self::TEXT_ATTRIBUTES));
        preg_match_all('/(?<![:\w@.-])(?:'.$attrs.')\s*=\s*"([^"]*)"/', $template, $statics, PREG_OFFSET_CAPTURE);
        foreach ($statics[1] as [$value, $pos]) {
            if ($this->isText($value) && ! $this->looksTechnical($value)) {
                $found[] = ['kind' => 'attribute', 'line' => $lineOf($pos), 'text' => $this->clip($value)];
            }
        }

        // Bound text attributes and v-tooltip: quoted literals not inside t().
        preg_match_all('/(?:(?::|v-bind:)(?:'.$attrs.')|v-tooltip(?:\.[\w.]+)?)\s*=\s*"([^"]*)"/', $template, $bound, PREG_OFFSET_CAPTURE);
        foreach ($bound[1] as [$expr, $pos]) {
            foreach ($this->bareLiterals($expr) as $literal) {
                $found[] = ['kind' => 'bound', 'line' => $lineOf($pos), 'text' => $this->clip($literal)];
            }
        }

        // toast / alert / confirm in template handlers.
        foreach ($this->scanCalls($template) as $hit) {
            $found[] = ['kind' => $hit['kind'], 'line' => $lineOf($hit['pos']), 'text' => $hit['text']];
        }

        usort($found, fn ($a, $b) => $a['line'] <=> $b['line']);

        return $found;
    }

    private function scanScript(string $code, int $lineOffset): array
    {
        $found = [];
        $lineOf = fn (int $pos) => $lineOffset + substr_count(substr($code, 0, $pos), "\n") + 1;
        $code = preg_replace_callback('/\/\*.*?\*\//s', fn ($c) => preg_replace('/[^\n]/', ' ', $c[0]), $code);
        $code = preg_replace_callback('/(?<![:\'"\\\\])\/\/[^\n]*/', fn ($c) => str_repeat(' ', strlen($c[0])), $code);

        foreach ($this->scanCalls($code) as $hit) {
            $found[] = ['kind' => $hit['kind'], 'line' => $lineOf($hit['pos']), 'text' => $hit['text']];
        }
        $fields = implode('|', self::TEXT_FIELDS);
        preg_match_all('/(?<![\w$])(?:'.$fields.')\s*:\s*([\'"`])((?:\\\\.|(?!\1).)*?)\1/s', $code, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($m as $hit) {
            $text = $this->unescape($hit[2][0], $hit[1][0]);
            if ($this->isText($text) && preg_match('/[A-Z]|\s/', $text) && ! $this->looksTechnical($text)) {
                $found[] = ['kind' => 'field', 'line' => $lineOf($hit[0][1]), 'text' => $this->clip($text)];
            }
        }

        return $found;
    }

    /** toast.x('…'), alert('…'), confirm('…'), prompt('…') with an unwrapped literal first argument. */
    private function scanCalls(string $code): array
    {
        $hits = [];
        preg_match_all('/(?<![\w$.])(?:(toast)(?:\.(?:success|error|warning|info|default))?|(?:window\.)?(alert|confirm|prompt))\(\s*([\'"`])((?:\\\\.|(?!\3).)*?)\3/s',
            $code, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($m as $hit) {
            $text = $this->unescape($hit[4][0], $hit[3][0]);
            if ($this->isText(preg_replace('/\$\{.*?\}/', ' ', $text))) {
                $hits[] = ['kind' => $hit[1][0] !== '' ? 'toast' : $hit[2][0], 'pos' => $hit[0][1], 'text' => $this->clip($text)];
            }
        }

        return $hits;
    }

    /** Quoted literals in an expression that a person would read and that are not an argument of t()/tc(). */
    private function bareLiterals(string $expr): array
    {
        $out = [];
        preg_match_all('/([\'`])((?:\\\\.|(?!\1).)*?)\1/s', $expr, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($m as $hit) {
            $before = rtrim(substr($expr, 0, $hit[0][1]));
            if (preg_match('/(?<![\w$.])\$?t[ck]?\($/', $before)) {
                continue;
            }
            $text = $this->unescape($hit[2][0], $hit[1][0]);
            if ($this->isText(preg_replace('/\$\{.*?\}/', ' ', $text)) && ! $this->looksTechnical($text)) {
                $out[] = $text;
            }
        }

        return $out;
    }

    private function scanPhp(string $source): array
    {
        $found = [];
        $patterns = [
            'flash' => '/->with\(\s*[\'"](?:success|error|message|warning|info|status)[\'"]\s*,\s*([\'"])((?:\\\\.|(?!\1).)*?)\1/s',
            'abort' => '/abort(?:_if|_unless)?\([^,]*?,?\s*4\d\d\s*,\s*([\'"])((?:\\\\.|(?!\1).)*?)\1/s',
            'message' => '/[\'"](?:message|error|warning|reason|detail)[\'"]\s*=>\s*([\'"])((?:\\\\.|(?!\1).)*?)\1/s',
            'validation' => '/withMessages\(\s*\[\s*[^]]*?=>\s*\[?\s*([\'"])((?:\\\\.|(?!\1).)*?)\1/s',
        ];
        foreach ($patterns as $kind => $pattern) {
            preg_match_all($pattern, $source, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
            foreach ($m as $hit) {
                $text = $this->unescape($hit[2][0], $hit[1][0]);
                if ($this->isText($text) && preg_match('/\s/', $text) && ! $this->looksTechnical($text)) {
                    $found[] = ['kind' => 'php-'.$kind, 'line' => substr_count(substr($source, 0, $hit[0][1]), "\n") + 1, 'text' => $this->clip($text)];
                }
            }
        }

        return $found;
    }

    /** PHP with its comments blanked out, line numbers kept. */
    private function phpWithoutComments(string $source): string
    {
        $out = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $out .= preg_replace('/[^\n]/', ' ', $token[1]);
            } else {
                $out .= is_array($token) ? $token[1] : $token;
            }
        }

        return $out;
    }

    /** Comments blanked out (examples in comments are not texts the page shows). */
    private function withoutComments(string $source): string
    {
        $blank = fn ($c) => preg_replace('/[^\n]/', ' ', $c[0]);
        $source = preg_replace_callback('/<!--.*?-->/s', $blank, $source);
        $source = preg_replace_callback('/\/\*.*?\*\//s', $blank, $source);

        return preg_replace_callback('/(?<![:\'"\\\\\w])\/\/[^\n]*/', $blank, $source);
    }

    /** A person-readable word: two letters in a row, not only a template/expression/code. */
    private function isText(string $text): bool
    {
        $text = trim($text);
        if ($text === '' || ! preg_match('/\p{L}{2,}/u', $text)) {
            return false;
        }

        return ! preg_match('/^(&[a-z]+;|[\w.-]+\(\)|https?:\/\/\S+|#[\w-]+|\$[\w.]+)$/i', $text);
    }

    /** Ids, class lists, routes, formats and other values nobody reads as text. */
    private function looksTechnical(string $text): bool
    {
        return (bool) preg_match('/^([a-z0-9_.:\/-]+|[a-z]+(-[a-z0-9]+)+(\s+[a-z!:\/\[\]0-9.-]+)*|[A-Z0-9_]+|\/\S*|[YMDHhmsaA\/:.\s-]+|application\/\S+|image\/\S+)$/', trim($text));
    }

    private function unescape(string $text, string $quote): string
    {
        return str_replace(['\\'.$quote, '\\n', '\\\\'], [$quote, "\n", '\\'], $text);
    }

    private function clip(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text));

        return mb_strlen($text) > 140 ? mb_substr($text, 0, 137).'…' : $text;
    }

    /** @return array<string, string> relative path => contents */
    private function files(array $dirs, array $names): array
    {
        $paths = array_filter(array_map(fn ($d) => $this->base.'/'.$d, $dirs), 'is_dir');
        if ($paths === []) {
            return [];
        }
        $out = [];
        foreach ((new Finder)->files()->in($paths)->name($names)->exclude(['node_modules', 'vendor']) as $file) {
            $out[ltrim(str_replace($this->base, '', $file->getRealPath()), '/')] = $file->getContents();
        }
        ksort($out);

        return $out;
    }
}
