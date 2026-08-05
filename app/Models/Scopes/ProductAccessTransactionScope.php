<?php

namespace App\Models\Scopes;

use App\Models\User;
use App\Support\ProductAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * "Access Product(s)" restriction for vend_transactions.
 *
 * A transaction is visible when ANY of its products is allowed - and when it
 * is visible, it is visible IN FULL, other parties' basket items included.
 * That is deliberate: shared machines mix SKUs, and a future basket-level
 * campaign discount cannot be attributed per-product, so half a basket would
 * be unexplainable. Cost price is the one thing masked, and that happens in
 * the exports, not here.
 *
 * Queue-safe: auth() is empty in jobs and cron, so this scope is inert there
 * by design (nightly rollups must see every product). The two CSV export jobs
 * are the exception and receive the id set explicitly.
 */
class ProductAccessTransactionScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        // The instanceof guard is what keeps machine-facing traffic unfiltered:
        // /api/v1/* runs on the api guard and the public refund form runs on no
        // guard at all, so auth() (web) is false there. A vending machine must
        // never be handed a partial planogram.
        if (! auth()->check() || ! auth()->user() instanceof User) {
            return;
        }

        $ids = ProductAccess::current();

        if ($ids === null) {
            return;
        }

        ProductAccess::applyToVendTransactions($builder, $ids, $model->getTable());
    }
}
