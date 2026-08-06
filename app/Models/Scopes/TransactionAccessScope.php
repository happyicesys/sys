<?php

namespace App\Models\Scopes;

use App\Models\User;
use App\Support\TransactionAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * "Transaction Access From" restriction for any table whose grain includes a
 * date: vend_transactions, vend_records, vend_product_records, gp_metrics.
 *
 * One scope class for all of them, parameterised by column — the predicate is
 * identical, only the column name differs (transaction_datetime / date /
 * txn_date). Registered as:
 *
 *     static::addGlobalScope(new TransactionAccessScope('transaction_datetime'));
 *     static::addGlobalScope(new TransactionAccessScope('date'));
 *     static::addGlobalScope(new TransactionAccessScope('txn_date'));
 *
 * The column is table-qualified at apply() time, not construction time, so the
 * same instance is safe on a query that joins another table carrying a column of
 * the same name — `date` in particular collides across half this schema and
 * would otherwise throw "Column 'date' in where clause is ambiguous". This is
 * the same lesson OperatorProductFilterScope learned with operator_id.
 *
 * Inert for anything that is not a logged-in web User — queue workers, cron,
 * the machine API, the public refund form. The nightly rollup jobs read these
 * same models, and a cut-off leaking into StoreVendsRecord would quietly stop
 * writing history.
 */
class TransactionAccessScope implements Scope
{
    public function __construct(private string $column = 'transaction_datetime')
    {
    }

    public function apply(Builder $builder, Model $model)
    {
        if (! auth()->check() || ! auth()->user() instanceof User) {
            return;
        }

        $from = TransactionAccess::current();

        if ($from === null) {
            return;
        }

        TransactionAccess::applyToColumn(
            $builder,
            $model->getTable() . '.' . $this->column,
            $from
        );
    }
}
