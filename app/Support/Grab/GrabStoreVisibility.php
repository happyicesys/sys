<?php

namespace App\Support\Grab;

use App\Models\Operator;
use App\Support\OperatorScope;

/**
 * Whose Grab stores a viewer sees (Grab Stores, Grab Errors). HIPL runs Grab for its
 * client operators too (DCVIC, KMY, ARK, KEA, NF are not in OperatorScope's sibling
 * group), as it did on the old Platform ID / Machines pages: HIPL staff see every
 * operator's stores, anyone else only their own.
 */
class GrabStoreVisibility
{
    public static function isHipl(): bool
    {
        return Operator::withoutGlobalScopes()->whereKey(auth()->user()?->operator_id)
            ->value('code') === OperatorScope::PARENT_CODE;
    }

    /** @return int[] */
    public static function operatorIds(): array
    {
        return self::isHipl()
            ? Operator::withoutGlobalScopes()->pluck('id')->map(fn ($id) => (int) $id)->all()
            : OperatorScope::current();
    }

    /** A request's Operator filter, narrowed to what the viewer may see. */
    public static function narrow($requested): array
    {
        $ceiling = self::operatorIds();
        $requestedIds = collect(is_array($requested) ? $requested : [$requested])
            ->reject(fn ($id) => $id === null || $id === '' || $id === 'all')
            ->map(fn ($id) => (int) $id)
            ->filter();

        return $requestedIds->isEmpty() ? $ceiling : array_values(array_intersect($ceiling, $requestedIds->all()));
    }

    public static function allows(int $operatorId): bool
    {
        return in_array($operatorId, self::operatorIds(), true);
    }
}
