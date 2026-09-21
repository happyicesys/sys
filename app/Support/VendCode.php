<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * A machine ID as people see it: optional letter prefix + the vends.code
 * number ("C6003", "2031"). The prefix exists only when another system owns
 * the number — today only CityBox OPS Pro — and is NULL for everything mark1
 * numbers itself, so a vending machine's label is exactly its code.
 */
final class VendCode
{
    /**
     * An OPS Pro machine name starts with 1–3 letters then the number:
     * "C6003", "C6004 HI Office", "C5001：29F 电梯旁". Anything else — their
     * old placeholder names "Singapore8", "#1" — carries no machine ID.
     */
    private const EXTERNAL_NAME = '/^\s*([A-Za-z]{1,3})[\s-]?(\d{1,9})(?!\d)/u';

    /** What a person types into a machine search: "C6003", "c60", "C", "6003". */
    private const SEARCH_TERM = '/^([A-Za-z]{1,3})(\d*)$/';

    public function __construct(
        public readonly ?string $prefix,
        public readonly int $number,
    ) {}

    /** Machine ID from an external system's name, or null when it has none. */
    public static function fromExternalName(?string $name): ?self
    {
        if ($name === null || ! preg_match(self::EXTERNAL_NAME, $name, $m)) {
            return null;
        }
        $number = (int) $m[2];

        return $number > 0 ? new self(strtoupper($m[1]), $number) : null;
    }

    public static function label(?string $prefix, int|string|null $code): string
    {
        return ($prefix ?? '').($code ?? '');
    }

    /**
     * The label as a SQL expression, for exports and reports that select the
     * machine ID straight off a join instead of hydrating a Vend:
     * `DB::raw(VendCode::sqlLabel().' AS vend_code')`. An unprefixed machine
     * yields exactly its code, so existing columns keep their values.
     */
    public static function sqlLabel(string $table = 'vends'): string
    {
        return "CONCAT(COALESCE({$table}.code_prefix, ''), {$table}.code)";
    }

    public function toLabel(): string
    {
        return self::label($this->prefix, $this->number);
    }

    /**
     * Exact match on a list of machine IDs as typed ("C6003", "2031"). A prefixed
     * term matches only that prefix; a bare number matches the number under any
     * prefix (the same "6003 finds both" rule as whereSearch).
     *
     * @param  array<int,string|int>  $labels
     */
    public static function whereLabels(Builder $query, array $labels, string $table = 'vends'): void
    {
        $terms = array_values(array_filter(array_map(fn ($t) => trim((string) $t), $labels), fn ($t) => $t !== ''));
        if ($terms === []) {
            return;
        }
        $query->where(function ($q) use ($terms, $table) {
            $bare = [];
            foreach ($terms as $term) {
                if (preg_match(self::SEARCH_TERM, $term, $m) && $m[2] !== '') {
                    $q->orWhere(fn ($w) => $w->where("{$table}.code_prefix", strtoupper($m[1]))->where("{$table}.code", (int) $m[2]));
                } else {
                    $bare[] = $term;
                }
            }
            if ($bare !== []) {
                $q->orWhereIn("{$table}.code", $bare);
            }
        });
    }

    /**
     * Apply a machine-code search box to a query on vends. Keeps the long-standing
     * behaviour for plain numbers (comma list = exact, else LIKE) and adds prefixed
     * terms: "C6003" matches only the C-prefixed 6003, "C60" every C-code starting
     * 60. A bare number still matches prefixed machines too, so typing 6003 finds
     * both a vending 6003 and chiller C6003 — their labels tell them apart.
     */
    public static function whereSearch(Builder $query, string $search, bool $contains = false, string $table = 'vends'): void
    {
        if (str_contains($search, ',')) {
            self::whereLabels($query, explode(',', $search), $table);

            return;
        }

        $search = trim($search);
        if (preg_match(self::SEARCH_TERM, $search, $m)) {
            $query->where("{$table}.code_prefix", strtoupper($m[1]));
            if ($m[2] !== '') {
                $query->where("{$table}.code", 'LIKE', ($contains ? '%' : '').$m[2].'%');
            }

            return;
        }

        $query->where("{$table}.code", 'LIKE', ($contains ? '%' : '').$search.'%');
    }
}
