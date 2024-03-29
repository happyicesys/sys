<?php

namespace App\Models;

use App\Models\Scopes\OperatorTransactionFilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendRecord extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::addGlobalScope(new OperatorTransactionFilterScope);
    }

    protected $fillable = [
        'customer_id',
        'customer_json',
        'date',
        'day',
        'failure_amount',
        'failure_count',
        'gross_profit',
        'month',
        'monthname',
        'online_failure_amount',
        'online_failure_count',
        'online_success_amount',
        'online_success_count',
        'operator_id',
        'revenue',
        'total_amount',
        'total_count',
        'vend_code',
        'vend_id',
        'year',
    ];

    protected $casts = [
        'customer_json' => 'json',
    ];

    // relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    // scopes
    public function scopeFilterIndex($query, $request)
    {
        $query = $query->when($request->has('visited'), function($query, $search) use ($request) {
            if($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            }else {
                $query->whereRaw('1 = 0');
            }
        })
        ->when($request->codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
            }else {
                $search = [$search];
            }
            $query->whereIn('vend_id', function($query) use ($search) {
                $query->select('id')->from('vends')->whereIn('code', $search);
            });
        })
        ->when($request->is_binded_customer, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->has('vend.customer');
                }else {
                    $query->doesntHave('vend.customer');
                }
            }
        })
        ->when($request->categories, function($query, $search) {
            $query->whereIn('vend_records.customer_id', function($query) use ($search) {
                $query->select('id')->from('customers')->whereIn('category_id', $search);
            });
        })
        ->when($request->categoryGroups, function($query, $search) {
            $query->whereIn('vend_records.customer_id', function($query) use ($search) {
                $query->select('id')->from('customers')->whereIn('category_id', function($query) use ($search) {
                    $query->select('id')->from('categories')->whereIn('category_group_id', $search);
                });
            });
            // $query->whereHas('customer.category.categoryGroup', function($query) use ($search) {
            //     $query->whereIn('id', $search);
            // });
        })
        ->when($request->customer, function($query, $search) {
            $query->whereIn('vend_records.customer_id', function($query) use ($search) {
                $query->select('id')
                    ->from('customers')
                    ->where('customers.virtual_customer_prefix', 'LIKE', "{$search}%")
                    ->orWhere('customers.virtual_customer_code', 'LIKE', "{$search}%")
                    ->orWhere('customers.name', 'LIKE', "%{$search}%");
                });
        })
        ->when($request->location_type_id, function($query, $search) {
            if($search != 'all') {
                $query->whereIn('customer_id', function($query) use ($search) {
                    $query->select('id')->from('customers')->where('location_type_id', $search);
                });
            }
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
                $query->where('vend_records.operator_id', $search);
            }
        });

        return $query;
    }
}
