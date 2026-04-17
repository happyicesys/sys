<?php

namespace App\Models;

use App\Models\Scopes\OperatorVendRecordScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendRecord extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::addGlobalScope(new OperatorVendRecordScope);
    }

    protected $fillable = [
        'all_total_count',
        'customer_id',
        'date',
        'day',
        'error_count',
        'failure_amount', //this failure amount is for failure transaction, other than [0, 6]
        'failure_count', //this failure count is for failure transaction, other than [0, 6]
        'gross_profit', //this gp is for success transaction
        'location_type_id',
        'month',
        'monthname',
        'online_failure_amount',
        'online_failure_count',
        'online_success_amount',
        'online_success_count',
        'operator_id',
        'revenue', //this revenue is for success transaction
        'total_amount', //this total amount is for success transaction
        'total_count', //this total count is for success transaction
        'vend_code',
        'vend_id',
        'vend_model_id',
        'vend_prefix_id',
        'year',
    ];

    // relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function locationType()
    {
        return $this->belongsTo(LocationType::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendPrefix()
    {
        return $this->belongsTo(VendPrefix::class);
    }

    public function vendModel()
    {
        return $this->belongsTo(VendModel::class);
    }

    // scopes

    public static function applyScope($query, $scope, ...$params)
    {
        return (new static)->$scope(...$params)->getQuery();
    }

    public function scopeFilterIndex($query, $request)
    {
        $query = $query->when($request->has('visited'), function ($query, $search) use ($request) {
            if ($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            } else {
                $query->whereRaw('1 = 0');
            }
        })
            ->when($request->codes, function ($query, $search) use ($request) {
                // Use pre-resolved IDs when available (set by DashboardController) to avoid
                // firing a subquery against vends on every chart query.
                if ($request->has('_resolved_vend_ids')) {
                    $query->whereIn('vend_id', $request->input('_resolved_vend_ids', []));
                } else {
                    $codes = strpos($search, ',') !== false
                        ? array_map('trim', explode(',', $search))
                        : [$search];
                    $query->whereIn('vend_id', function ($query) use ($codes) {
                        $query->select('id')->from('vends')->whereIn('code', $codes);
                    });
                }
            })
            ->when($request->is_binded_customer, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->has('vend.customer');
                    } else {
                        $query->doesntHave('vend.customer');
                    }
                }
            })
            ->when($request->categories, function ($query, $search) {
                $query->whereIn('vend_records.customer_id', function ($query) use ($search) {
                    $query->select('id')->from('customers')->whereIn('category_id', $search);
                });
            })
            ->when($request->categoryGroups, function ($query, $search) {
                $query->whereIn('vend_records.customer_id', function ($query) use ($search) {
                    $query->select('id')->from('customers')->whereIn('category_id', function ($query) use ($search) {
                        $query->select('id')->from('categories')->whereIn('category_group_id', $search);
                    });
                });
                // $query->whereHas('customer.category.categoryGroup', function($query) use ($search) {
                //     $query->whereIn('id', $search);
                // });
            })
            ->when($request->customer, function ($query, $search) {
                $query->whereIn('vend_records.customer_id', function ($query) use ($search) {
                    $query->select('id')
                        ->from('customers')
                        ->where('customers.virtual_customer_prefix', 'LIKE', "{$search}%")
                        ->orWhere('customers.virtual_customer_code', 'LIKE', "{$search}%")
                        ->orWhere('customers.name', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->location_type_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vend_records.location_type_id', $search);
                }
            })
            ->when($request->operator_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vend_records.operator_id', $search);
                }
            })
            ->when($request->operators, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('vend_records.operator_id', $search);
                }
            })
            ->when($request->vendModels, function ($query, $search) {
                $query->whereIn('vend_records.vend_model_id', $search);
            })
            ->when($request->vendPrefixes, function ($query, $search) {
                if (in_array('single-ud', $search)) {
                    $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                    unset($search[array_search('single-ud', $search)]);
                }
                $query->whereIn('vend_records.vend_prefix_id', $search);
            });

        return $query;
    }
}
