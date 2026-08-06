<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Transaction Access From" — resolves the EARLIEST date a viewer may see sales for.
 *
 * The time-dimension twin of [[ProductAccess]]. Two cut-offs compose, and the
 * Operator one is a hard floor the user can only push LATER, never earlier:
 *
 *   Operator 2026-01-01 + User blank        => 2026-01-01   (inherits the floor)
 *   Operator 2026-01-01 + User 2026-06-01   => 2026-06-01   (the later one wins)
 *   Operator 2026-01-01 + User 2025-01-01   => 2026-01-01   (user cannot go earlier)
 *   Operator blank      + User 2026-06-01   => 2026-06-01
 *   Operator blank      + User blank        => null          (sees everything)
 *
 * ==========================================================================
 * HOW THIS DIFFERS FROM ProductAccess — read before copying its idioms
 * ==========================================================================
 * ProductAccess has THREE states (null / [] / [ids]) and its docblock shouts
 * about never using truthiness, because `[]` means "sees nothing" and is falsy.
 *
 * This class has only TWO states:
 *
 *      null    = unrestricted, add no predicate at all
 *      'Y-m-d' = restricted, add `column >= that date`
 *
 * There is no "sees nothing" sentinel, because a date always describes a
 * non-empty half-open range — a cut-off in the future simply returns no rows,
 * which is data, not a special case. So truthiness IS safe here (a non-empty
 * date string is always truthy). It is still written as `!== null` throughout
 * to match ProductAccess, so the two read the same way side by side.
 * ==========================================================================
 *
 * NO OPERATOR EXEMPTION, deliberately.
 * ProductAccess exempts the HappyIce operator via
 * config('access.product_unrestricted_operator_ids') because product
 * allow-lists are derived from operator OWNERSHIP, and the superuser group owns
 * everything. A date cut-off is never derived from anything — an admin typed it
 * into a box. If someone sets a date on the HappyIce operator they meant it, and
 * silently ignoring it would be the surprising behaviour.
 */
class TransactionAccess
{
    /** Sentinel for "we resolved this user and the answer was unrestricted". */
    private const UNRESTRICTED = 'unrestricted';

    /** The column, on both `users` and `operators`. */
    public const COLUMN = 'transaction_access_from';

    /**
     * Per-request memo, keyed by user id. Mandatory: a global scope runs on
     * every Eloquent query in the request, so this must never re-query.
     *
     * @var array<int, string>
     */
    private static array $memo = [];

    /**
     * Effective earliest visible date for a user.
     *
     * @return string|null 'Y-m-d', or null = unrestricted
     */
    public static function forUser(User $user): ?string
    {
        $key = (int) $user->getKey();

        if (array_key_exists($key, self::$memo)) {
            $cached = self::$memo[$key];

            return $cached === self::UNRESTRICTED ? null : $cached;
        }

        try {
            $resolved = self::resolve($user);
        } catch (Throwable $e) {
            // Fail OPEN, deliberately — same rationale as ProductAccess::forUser().
            //
            // This runs inside global scopes, i.e. on essentially every query in
            // the app. If the column this reads is missing — code deployed ahead
            // of `php artisan migrate` — throwing here would take the whole site
            // down. Returning null means "unrestricted", which is exactly how the
            // app behaved before this feature existed.
            //
            // The trade-off is explicit and is the same one the product feature
            // made: a broken migration state degrades to pre-feature behaviour
            // rather than an outage. Logged so it is visible rather than silent.
            Log::warning('TransactionAccess resolution failed; treating viewer as unrestricted', [
                'user_id' => $key,
                'error' => $e->getMessage(),
            ]);

            $resolved = null;
        }

        self::$memo[$key] = $resolved === null ? self::UNRESTRICTED : $resolved;

        return $resolved;
    }

    /**
     * Effective earliest visible date for whoever is logged in right now.
     *
     * Returns null (unrestricted) for anything that is not a logged-in web
     * User: machines on auth:api, the public refund form, queue workers, cron.
     * Those paths MUST stay unfiltered — the nightly rollups and the vending
     * machines themselves read through here, and a cut-off applied to
     * StoreVendsRecord would silently stop writing history.
     */
    public static function current(): ?string
    {
        if (! auth()->check()) {
            return null;
        }

        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        return self::forUser($user);
    }

    /** Is the current viewer date-restricted at all? */
    public static function isRestricted(): bool
    {
        return self::current() !== null;
    }

    /**
     * The operator-level floor, used by the User edit screen to render the
     * "capped by operator" notice and to reject an earlier date on save.
     */
    public static function operatorFloor(?int $operatorId): ?string
    {
        if (! $operatorId) {
            return null;
        }

        try {
            return self::normalise(
                DB::table('operators')->where('id', $operatorId)->value(self::COLUMN)
            );
        } catch (Throwable $e) {
            // Same fail-open rationale as forUser().
            return null;
        }
    }

    /**
     * Apply the cut-off to any table carrying a DATE or DATETIME column:
     * vend_transactions.transaction_datetime, vend_records.date,
     * vend_product_records.date, gp_metrics.txn_date, ...
     *
     * Deliberately a plain `>=` on the bare column rather than a DATE() wrap or
     * a COALESCE — both would make the predicate non-sargable and throw away the
     * indexes every one of these tables depends on. For a DATETIME column MySQL
     * coerces 'YYYY-MM-DD' to midnight, which is exactly the boundary we want:
     * the chosen day is INCLUDED.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  string|null  $from  omit to resolve from the session
     */
    public static function applyToColumn($query, string $column, ?string $from = null)
    {
        if (func_num_args() < 3) {
            $from = self::current();
        }

        if ($from === null) {
            return $query;
        }

        return $query->where($column, '>=', $from);
    }

    /**
     * A ready-to-interpolate SQL fragment, for the raw DB::raw() derived tables
     * no Eloquent scope can reach inside (the Ops Dashboard stock columns, the
     * report channel-usage subqueries, the active-machine graph).
     *
     * Returns '' when unrestricted, so the SQL stays byte-identical to today for
     * every unrestricted user.
     *
     * The date is re-formatted through Carbon rather than interpolated raw:
     * it originates from a form field, and this lands inside raw SQL text.
     *
     * @param  string|null  $from  omit to resolve from the session
     */
    public static function sqlFilter(string $column, ?string $from = null): string
    {
        if (func_num_args() < 2) {
            $from = self::current();
        }

        if ($from === null) {
            return '';
        }

        return " AND {$column} >= '" . self::normalise($from) . "'";
    }

    /**
     * Is this single date visible to the viewer? For per-row masking in the
     * exports, and for the "is this whole report empty for them?" checks.
     *
     * @param  mixed  $date
     */
    public static function allows(?string $from, $date): bool
    {
        if ($from === null) {
            return true;
        }

        if ($date === null) {
            return false;
        }

        return self::normalise($date) >= $from;
    }

    /** Drop a memoised user (call after writing either column). */
    public static function flush(?int $userId = null): void
    {
        if ($userId === null) {
            self::$memo = [];

            return;
        }

        unset(self::$memo[$userId]);
    }

    // ---------------------------------------------------------------- internals

    private static function resolve(User $user): ?string
    {
        $operatorId = $user->operator_id ? (int) $user->operator_id : null;

        $operatorFrom = $operatorId
            ? self::normalise(DB::table('operators')->where('id', $operatorId)->value(self::COLUMN))
            : null;

        $userFrom = self::normalise($user->getAttribute(self::COLUMN));

        return self::later($operatorFrom, $userFrom);
    }

    /**
     * The later of two nullable dates — null being "no opinion", NOT "earliest".
     *
     * This is the whole composition rule: a restriction on either side always
     * wins over the other side's silence, and when both speak the tighter
     * (later) one wins. Never returns a date earlier than the operator's, which
     * is what makes the operator a floor rather than a default.
     */
    private static function later(?string $a, ?string $b): ?string
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return $a >= $b ? $a : $b;
    }

    /**
     * Coerce whatever the caller has — Carbon, DateTime, 'Y-m-d',
     * 'Y-m-d H:i:s', '' — to a bare 'Y-m-d', or null.
     *
     * Empty string matters: an untouched HTML date input posts '', and treating
     * that as a date would restrict everyone to 1970.
     *
     * @param  mixed  $value
     */
    public static function normalise($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->toDateString();
        } catch (Throwable $e) {
            return null;
        }
    }
}
