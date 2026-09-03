<?php

namespace App\Support;

use App\Models\Operator;
use App\Models\Scopes\OperatorFilterScope;
use App\Models\User;

/**
 * Operator visibility — resolves WHICH operators the current viewer may see.
 *
 * This is the single definition of the rule that Vend/CustomerIndex has always
 * enforced inline (VendController::indexCustomer, ::index and
 * ::customerIndexAggregates):
 *
 *      HIPL staff  => HIPL + its sibling operators (HIMD, LEA, HIESG, UL-ST, CB)
 *      everyone else => their own operator, and nothing else
 *
 * ==========================================================================
 * READ THIS BEFORE TOUCHING ANY CALLER
 * ==========================================================================
 * forUser()/current() return the viewer's CEILING, never a preference. A
 * request-supplied operator filter may only NARROW it — never widen it — so
 * every caller must run the requested ids through narrow(), or apply the
 * ceiling as its own predicate before the user filter.
 *
 * An EMPTY array means "sees nothing" and must still be applied: passing []
 * to whereIn() yields zero rows, which is correct. Never guard the predicate
 * with truthiness (`if ($ids)`), because that turns "sees nothing" into "sees
 * everything" — a silent cross-operator data leak.
 * ==========================================================================
 */
class OperatorScope
{
    /** Operator code whose staff can see the whole sibling group. */
    public const PARENT_CODE = 'HIPL';

    /**
     * HIPL and the operators it administers on the same tenancy. Kept in one
     * place so adding a sibling is a one-line change instead of a grep.
     *
     * CB (Citybox) joined 2026-09-03: the Smart Chillers sit under their own
     * operator so settlement never mixes with vending, but HIPL ops staff run
     * them from the same Dashboard and Machines index — without CB here the
     * default (empty) Operator filter hid every chiller from them.
     */
    public const PARENT_GROUP_CODES = ['HIPL', 'HIMD', 'LEA', 'HIESG', 'UL-ST', 'CB'];

    /**
     * Per-request memo, keyed by user id — the ceiling is asked for several
     * times per page (query + options + guards) and must not re-query.
     *
     * @var array<int, array<int, int>>
     */
    private static array $memo = [];

    /**
     * Operator ids the given user may see.
     *
     * @return array<int, int> [] = sees nothing
     */
    public static function forUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $key = (int) $user->getKey();

        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }

        // NOT $user->operator: that relation inherits OperatorFilterScope,
        // which pins the lookup to auth()->user()->operator_id and so returns
        // null whenever $user is anyone other than the authenticated user -
        // silently memoising an empty ceiling for them. OperatorActiveScope is
        // dropped too, so a user whose own operator has been deactivated still
        // sees their own campaigns instead of a blank page (they still cannot
        // see anyone else's).
        $operator = Operator::withoutGlobalScopes()->find((int) $user->operator_id);

        if (! $operator) {
            return self::$memo[$key] = [];
        }

        if ($operator->code !== self::PARENT_CODE) {
            return self::$memo[$key] = [(int) $operator->id];
        }

        // OperatorFilterScope pins non-HIPL viewers to their own operator row;
        // drop it here so the group lookup is a property of the code list and
        // not of whoever happens to be authenticated. OperatorActiveScope is
        // deliberately kept — a deactivated sibling is out of scope.
        return self::$memo[$key] = Operator::withoutGlobalScope(OperatorFilterScope::class)
            ->whereIn('code', self::PARENT_GROUP_CODES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Operator ids the authenticated user may see.
     *
     * @return array<int, int> [] = sees nothing
     */
    public static function current(): array
    {
        return self::forUser(auth()->user());
    }

    /**
     * Constrain a request-supplied operator filter to the viewer's ceiling.
     *
     * Accepts whatever the filter widgets post: null, the string 'all', a
     * single id, or an array of ids. Anything that is not an explicit,
     * in-scope selection collapses to the full ceiling.
     *
     * @param  mixed  $requested
     * @return array<int, int> [] = sees nothing
     */
    public static function narrow($requested, ?User $user = null): array
    {
        $ceiling = $user ? self::forUser($user) : self::current();

        if ($requested === null || $requested === '' || $requested === 'all') {
            return $ceiling;
        }

        $requestedIds = collect(is_array($requested) ? $requested : [$requested])
            ->reject(fn ($id) => $id === null || $id === '' || $id === 'all')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->all();

        if (empty($requestedIds)) {
            return $ceiling;
        }

        return array_values(array_intersect($ceiling, $requestedIds));
    }

    /**
     * Whether the viewer may act on a row belonging to the given operator.
     */
    public static function allows(?int $operatorId, ?User $user = null): bool
    {
        if ($operatorId === null) {
            return false;
        }

        return in_array($operatorId, $user ? self::forUser($user) : self::current(), true);
    }

    /**
     * Flush the memo — for tests that act as more than one user per process.
     */
    public static function flush(): void
    {
        self::$memo = [];
    }
}
