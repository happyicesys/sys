<?php

namespace App\Models;

use App\Models\Scopes\OperatorVendFilterScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMovement extends Model
{
    use HasFactory;

    const TYPE_INCOMING = 1;

    const TYPE_ADJUSTMENT = 2;

    const TYPE_PICKED = 3;

    const TYPE_UNDO_PICKED = 4;

    protected $fillable = [
        'product_id',
        'type',
        'qty',
        'operator_id',
        'user_id',
        'remarks',
        'batch_number',
        'created_at',
    ];

    /**
     * The viewer's operator boundary for the Incoming Stock pages (2026-10-17, audit UI-02
     * follow-up): an operator-restricted viewer reads only its own operator's movements, HIPL all.
     * A local scope on purpose, not a global one: ops-job pick / unpick read movements across
     * operators (operator-1 jobs carry sibling operators' machines).
     */
    public function scopeVisibleToViewer(Builder $query): Builder
    {
        $operatorId = OperatorVendFilterScope::viewerOperatorId();

        return $operatorId === null ? $query : $query->where($this->getTable().'.operator_id', $operatorId);
    }

    /**
     * A request's operator filter is a preference, never an entitlement: a restricted viewer is
     * held to its own operator whatever the request names.
     *
     * @param  array<int, int|string>  $operators
     * @return array<int, int|string>
     */
    public static function capOperatorsToViewer(array $operators): array
    {
        $operatorId = OperatorVendFilterScope::viewerOperatorId();

        return $operatorId === null ? $operators : [$operatorId];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
