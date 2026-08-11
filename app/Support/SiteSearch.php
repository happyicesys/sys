<?php

namespace App\Support;

use App\Models\Customer;

/**
 * The one place that defines what the "Site" free-text filter matches.
 *
 * Every Site box in mark1 (Dashboard, Vend, Reports, Ops, Customer, …) funnels
 * through here so a term behaves identically wherever it is typed. A term is
 * matched against, in one OR group:
 *
 *  - the virtual customer prefix   ("LK", "MCST")            prefix match
 *  - the virtual customer code     (10025)                   prefix match, numeric terms only
 *  - the CMS customer code         ("16789 (CEZ)")           prefix match
 *  - the site name                 ("Watercove")             contains
 *  - the displayed **Site ID**     (24310 = customers.id + Customer::RUNNING_NUMBER_INIT)
 *  - the "PREFIX-CODE" shorthand   ("LK-10025")
 *
 * A leading numeric token is read as the Site ID and the remainder as a name
 * fragment, so "24310", "24310 Waterc" and "Waterc" all work. A number below
 * RUNNING_NUMBER_INIT is *not* a Site ID — it stays a name fragment, so
 * searching "35" still finds "Blk 35".
 *
 * Usage:
 *
 *   SiteSearch::for($request->customer)->applyTo($query);                  // customers
 *   SiteSearch::for($search)->on('c')->applyTo($query);                    // customers as c
 *   SiteSearch::for($search)->alsoMatching('vend_prefixes.name')->applyTo($query);
 *
 * The predicate is always wrapped in its own nested group, so it is safe to
 * chain onto a builder that already carries other constraints.
 */
final class SiteSearch
{
    private function __construct(
        private readonly string $term,
        private readonly string $table,
        private readonly array $extraColumns,
    ) {}

    public static function for(?string $term): self
    {
        return new self(trim((string) $term), 'customers', []);
    }

    /**
     * Qualify the predicate against a different table name or alias
     * (e.g. "customers as c" => on('c')).
     */
    public function on(string $table): self
    {
        return new self($this->term, $table, $this->extraColumns);
    }

    /**
     * Additional already-qualified columns to prefix-match, for queries that
     * join a machine prefix table alongside customers.
     */
    public function alsoMatching(string ...$columns): self
    {
        return new self($this->term, $this->table, array_values(array_unique(
            array_merge($this->extraColumns, $columns)
        )));
    }

    public function isEmpty(): bool
    {
        return $this->term === '';
    }

    /**
     * Translate a displayed Site ID into customers.id, or null when the term
     * is not a Site ID.
     */
    public static function customerId(?string $term): ?int
    {
        $term = trim((string) $term);

        // customers.id starts at 1, so RUNNING_NUMBER_INIT itself is not a
        // valid Site ID.
        if ($term === '' || ! ctype_digit($term) || (int) $term <= Customer::RUNNING_NUMBER_INIT) {
            return null;
        }

        return (int) $term - Customer::RUNNING_NUMBER_INIT;
    }

    /**
     * Apply the predicate to any Eloquent or query builder rooted at (or
     * joined to) the customers table. A blank term is a no-op.
     *
     * @template TQuery
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function applyTo($query)
    {
        if ($this->isEmpty()) {
            return $query;
        }

        $term = $this->term;
        $table = $this->table;
        $extraColumns = $this->extraColumns;

        // Leading numeric token => Site ID, remainder => name fragment.
        $siteId = null;
        $namePart = '';

        if (preg_match('/^(\d+)\s*(.*)$/', $term, $matches)) {
            $siteId = self::customerId($matches[1]);
            $namePart = trim($matches[2]);
        }

        [$dashPrefix, $dashCode] = self::splitPrefixCode($term);

        $query->where(function ($query) use ($term, $table, $extraColumns, $siteId, $namePart, $dashPrefix, $dashCode) {
            $query->where($table.'.virtual_customer_prefix', 'LIKE', $term.'%')
                ->orWhere($table.'.code', 'LIKE', $term.'%')
                ->orWhere($table.'.name', 'LIKE', '%'.$term.'%');

            // virtual_customer_code is an INT column. Comparing a non-numeric
            // term forces MySQL to cast every row (and can never match), so
            // only reach for it when the term is actually numeric.
            if (ctype_digit($term)) {
                $query->orWhere($table.'.virtual_customer_code', 'LIKE', $term.'%');
            }

            // "LK-10025" shorthand.
            if ($dashPrefix !== null) {
                $query->orWhere(fn ($sub) => $sub
                    ->where($table.'.virtual_customer_prefix', $dashPrefix)
                    ->where($table.'.virtual_customer_code', 'LIKE', $dashCode.'%'));
            }

            foreach ($extraColumns as $column) {
                $query->orWhere($column, 'LIKE', $term.'%');
            }

            // The Site ID shown in the UI.
            if ($siteId !== null) {
                if ($namePart !== '') {
                    $query->orWhere(fn ($sub) => $sub
                        ->where($table.'.id', $siteId)
                        ->where($table.'.name', 'LIKE', '%'.$namePart.'%'));
                } else {
                    $query->orWhere($table.'.id', $siteId);
                }
            }
        });

        return $query;
    }

    /**
     * Split a "PREFIX-CODE" term. Returns [null, null] when the term is not in
     * that shape.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function splitPrefixCode(string $term): array
    {
        if (! str_contains($term, '-')) {
            return [null, null];
        }

        [$prefix, $code] = array_pad(explode('-', $term, 2), 2, '');
        $prefix = trim($prefix);
        $code = trim($code);

        if ($prefix === '' || $code === '' || ! ctype_digit($code)) {
            return [null, null];
        }

        return [$prefix, $code];
    }
}
