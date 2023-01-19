<?php

namespace App\Models;

use App\Models\Scopes\OperatorTransactionFilterScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GetUserTimezone;

class VendTransaction extends Model
{
    use GetUserTimezone, HasFactory;

    protected static function booted()
    {
        static::addGlobalScope(new OperatorTransactionFilterScope);
    }

    protected $casts = [
        'transaction_datetime' => 'datetime',
        'vend_transaction_json' => 'json',
    ];

    protected $fillable = [
        'order_id',
        'transaction_datetime',
        'amount',
        'payment_method_id',
        'product_id',
        'vend_channel_id',
        'vend_channel_error_id',
        'vend_id',
        'vend_transaction_json',
    ];

    // relationships
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendChannel()
    {
        return $this->belongsTo(VendChannel::class);
    }

    public function vendChannelError()
    {
        return $this->belongsTo(VendChannelError::class);
    }

    // scopes
    public function scopeFilterTransactionIndex($query, $request)
    {
        $sortKey = $request->sortKey ? $request->sortKey : 'transaction_datetime';
        $sortBy = $request->sortBy ? $request->sortBy : false;
        $startDate =  $request->date_from ? Carbon::parse($request->date_from)->toDateString() : Carbon::today()->subDays(1)->toDateString();
        $endDate =  $request->date_to ? Carbon::parse($request->date_to)->toDateString() : Carbon::today()->toDateString();
        // dd($startDate, $endDate);
        // return
        $query =  $query->when($request->codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
            }else {
                $search = [$search];
            }
            $query->whereHas('vend', function($query) use ($search) {
                $query->whereIn('code', $search);
            });
        })
        ->when($request->channel_code, function($query, $search) {
            $query->whereHas('vendChannel', function($query) use ($search) {
                $query->where('code', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->errors, function($query, $search) {
            if(in_array('errors_only', $search)) {
                $query->has('vendChannelError');
            }else {
                $query->whereHas('vendChannelError', function($query) use ($search) {
                    $query->whereIn('id', $search);
                });
            }
        })
        ->when($request->paymentMethod, function($query, $search) {
            $query->where('payment_method_id', $search);
        })
        ->when($request->categories, function($query, $search) {
            $query->whereHas('vend.latestVendBinding.customer.category', function($query) use ($search) {
                $query->whereIn('id', $search);
            });
        })
        ->when($request->categoryGroups, function($query, $search) {
            $query->whereHas('vend.latestVendBinding.customer.category.categoryGroup', function($query) use ($search) {
                $query->whereIn('id', $search);
            });
        })
        ->when($request->customer_code, function($query, $search) {
            $query->whereHas('vend.latestVendBinding.customer', function($query) use ($search) {
                $query->where('code', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->customer_name, function($query, $search) {
            $query->where(function($query) use ($search) {
                $query
                    ->whereHas('vend.latestVendBinding.customer', function($query) use ($search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('vend', function($query) use ($search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    });
            });
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('vend.operators', function($query) use ($search) {
                    $query->where('operators.id', $search);
                });
            }
        })
        ->when($startDate, function($query, $search) {
            $query->whereDate('transaction_datetime', '>=', $search);
        })
        ->when($endDate, function($query, $search) {
            $query->whereDate('transaction_datetime', '<=', $search);
        })
        ->when($sortKey, function($query, $search) use ($sortBy) {
            $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
        });

        return $query;
    }
}
