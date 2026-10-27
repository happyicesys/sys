<?php

namespace App\Support;

use App\Models\User;
use App\Models\Vend;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Support\Facades\DB;

/**
 * Which machines a user may see — the third access level, under Operator and
 * Access Product(s) (Brian, 2026-10-27: "operator, sku, machine").
 *
 *   ALL     no machine limit.
 *   ONLY    the user's bound machines (`user_vend`, User edit → "Vending Machine
 *           to Bind"). An empty list means ALL — the rule since the start.
 *   EXCEPT  every machine but the ones switched to "No Show" (`user_hidden_vends`).
 *           A user limited to a product list ("Only the product(s) listed below")
 *           is on this mode: User edit lists the machines carrying their
 *           products live, each Show (default) / No Show. A deny-list, not a
 *           live allow-list, so a product owner keeps the history of a machine
 *           that has since stopped carrying their product.
 *
 * Every reader that limits by machine goes through here: the transaction /
 * record / UI Setting / delivery scopes, App\Traits\HasFilter, the dashboard
 * and report raw queries, the export jobs and Warehouse Qty's machine counts.
 * Never read `$user->vends` to restrict a query again.
 *
 * Resolved once per user per request or job (VendAccessResolver is scoped in
 * the container); writers call flush().
 */
final class VendAccess
{
    public const ALL = 'all';

    public const ONLY = 'only';

    public const EXCEPT = 'except';

    /** @var array<int, int>|null */
    private ?array $siteIds = null;

    /**
     * @param  array<int, int>  $vendIds  ONLY: the allowed machines; EXCEPT: the hidden ones
     */
    public function __construct(public readonly string $mode, public readonly array $vendIds = []) {}

    public static function forUser(?User $user): self
    {
        return app(VendAccessResolver::class)->for($user);
    }

    /** The authenticated user's machine access; ALL when nobody is logged in. */
    public static function current(): self
    {
        $user = auth()->user();

        return self::forUser($user instanceof User ? $user : null);
    }

    public static function flush(?int $userId = null): void
    {
        app(VendAccessResolver::class)->flush($userId);
    }

    public function isUnrestricted(): bool
    {
        return $this->mode === self::ALL;
    }

    public function allows(?int $vendId): bool
    {
        return match ($this->mode) {
            self::ONLY => in_array($vendId, $this->vendIds, true),
            self::EXCEPT => ! in_array($vendId, $this->vendIds, true),
            default => true,
        };
    }

    /** ONLY's machine ids, else null — for a caller that can only express an allow-list. */
    public function onlyVendIds(): ?array
    {
        return $this->mode === self::ONLY ? $this->vendIds : null;
    }

    /**
     * Narrow a query to the machines this user may see.
     *
     * $siteColumn (ONLY only) also keeps rows to the bound machines' CURRENT
     * Sites — what the transaction and record scopes have always done, so a
     * machine moved to another Site does not carry its old Site's sales along.
     */
    public function constrain(BuilderContract $query, string $vendColumn, ?string $siteColumn = null): void
    {
        if ($this->mode === self::ONLY) {
            $query->whereIn($vendColumn, $this->vendIds);

            if ($siteColumn !== null && ($siteIds = $this->siteIds()) !== []) {
                $query->whereIn($siteColumn, $siteIds);
            }

            return;
        }

        if ($this->mode === self::EXCEPT) {
            $query->where(fn ($q) => $q->whereNull($vendColumn)->orWhereNotIn($vendColumn, $this->vendIds));
        }
    }

    /**
     * The Sites the ONLY machines sit at now ([] in any other mode).
     *
     * @return array<int, int>
     */
    public function siteIds(): array
    {
        if ($this->mode !== self::ONLY) {
            return [];
        }

        return $this->siteIds ??= DB::table('vends')
            ->whereIn('id', $this->vendIds)
            ->whereNotNull('customer_id')
            ->pluck('customer_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
