<?php

namespace App\Support;

use App\Models\Operator;

/**
 * Whose products an operator works with (Brian, 2026-10-27).
 *
 * Products belong to one operator (`products.operator_id`), but the HIPL group
 * (OperatorScope::PARENT_GROUP_CODES) sells HIPL's catalogue: HIMD owns no
 * product at all, every one of its machines carries HIPL's. So a member of
 * that group works with its OWN products plus the group head's (HIPL's) —
 * never another member's: CityBox, XO, LEA and MSW keep their catalogues apart.
 *
 *      HIPL            => HIPL
 *      HIPL's group    => itself + HIPL
 *      anyone else     => itself
 *
 * Used by OperatorProductFilterScope (what a viewer may see — HIPL itself stays
 * unrestricted there), by every Operator filter on a product list (picking
 * HIMD lists the products HIMD sells), and by the Access Product(s) lists of
 * User / Operator edit. The user's own allow-list (ProductAccess) still
 * narrows on top.
 *
 * Scoped in the container (AppServiceProvider): memoised for one request or
 * job, since Product's global scope asks on every product query.
 */
class ProductOwners
{
    /** @var array<int, array<int, int>> */
    private array $memo = [];

    /**
     * @return array<int, int> never empty: an operator not on file keeps its own id
     */
    public function of(int $operatorId): array
    {
        return $this->memo[$operatorId] ??= $this->resolve($operatorId);
    }

    /**
     * An Operator filter's selection, widened to the products each picked
     * operator works with.
     *
     * @param  iterable<int|string>  $operatorIds
     * @return array<int, int>
     */
    public function forSelection(iterable $operatorIds): array
    {
        return collect($operatorIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->flatMap(fn (int $id) => $this->of($id))
            ->unique()
            ->values()
            ->all();
    }

    private function resolve(int $operatorId): array
    {
        // Without global scopes: OperatorFilterScope would pin the lookup to the
        // viewer's own row (hiding HIPL from a HIMD viewer), and a deactivated
        // operator still owns what it owns.
        $operators = Operator::withoutGlobalScopes()
            ->where('id', $operatorId)
            ->orWhere('code', OperatorScope::PARENT_CODE)
            ->pluck('code', 'id');

        $code = $operators->get($operatorId);
        $headId = $operators->search(OperatorScope::PARENT_CODE);

        if ($code === null
            || $code === OperatorScope::PARENT_CODE
            || $headId === false
            || ! in_array($code, OperatorScope::PARENT_GROUP_CODES, true)) {
            return [$operatorId];
        }

        return [$operatorId, (int) $headId];
    }
}
