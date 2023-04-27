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
        'product_json' => 'json',
        'transaction_datetime' => 'datetime',
        'unit_cost_json' => 'json',
        'vend_json' => 'json',
        'vend_transaction_json' => 'json',
    ];

    protected $fillable = [
        'order_id',
        'transaction_datetime',
        'amount',
        'gross_profit',
        'gross_profit_margin',
        'is_payment_received',
        'payment_method_id',
        'product_id',
        'product_json',
        'revenue',
        'unit_cost_json',
        'vend_channel_id',
        'vend_channel_error_id',
        'vend_id',
        'vend_json',
        'vend_transaction_json',
        'unit_cost',
        'unit_cost_id',
    ];

    // relationships
    public function getGrossProfit()
    {
        return $this->getRevenue() - $this->getUnitCost();
    }

    public function getRevenue()
    {
        return $this->amount/(1.00 + ($this->product && $this->product->operator && $this->product->operator->gst_vat_rate ? $this->product->operator->gst_vat_rate/100 : 0));
    }

    public function getUnitCost()
    {
        return $this->unitCost ? $this->unitCost->cost : 0;
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unitCost()
    {
        return $this->belongsTo(UnitCost::class);
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
        $isBindedCustomer = $request->is_binded_customer != null ? $request->is_binded_customer : 'true';
        $isBindedCustomer = 'all';
        $isPaymentReceived = $request->is_payment_received != null ? $request->is_payment_received : 'all';

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
        ->when($request->channel_codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
            }else {
                $search = [$search];
            }
            $query->whereHas('vendChannel', function($query) use ($search) {
                $query->whereIn('code', $search);
            });
        })
        ->when($request->errors, function($query, $search) {
            // dd($search);
            if(in_array('errors_only', $search)) {
                $query->has('vendChannelError');
            }else if(in_array('1', $search)) {
                $query->where(function($query) {
                    $query->whereHas('vendChannelError', function($query) {
                        $query->where('id', 1);
                    })->orWhereDoesntHave('vendChannelError');
                });
            }else {
                $query->whereHas('vendChannelError', function($query) use ($search) {
                    $query->whereIn('id', $search);
                });
            }
        })
        ->when($isBindedCustomer, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->has('vend.latestVendBinding');
                }else {
                    $query->doesntHave('vend.latestVendBinding');
                }
            }
        })
        ->when($isPaymentReceived, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->where('is_payment_received', true);
                }else {
                    $query->where('is_payment_received', false);
                }
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
            $query
                ->whereHas('vend.latestVendBinding.customer', function($query) use ($search) {
                    $query->where('name', 'LIKE', "%{$search}%");
                })
                ->orWhereHas('vend', function($query) use ($search) {
                    $query->where('name', 'LIKE', "%{$search}%");
                });
        })
        ->when($request->location_type_id, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('vend.latestVendBinding.customer', function($query) use ($search) {
                    $query->where('location_type_id', $search);
                });
            }
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('vend.operators', function($query) use ($search) {
                    $query->where('operators.id', $search);
                });
            }
        })
        ->when($request->date_from, function($query, $search) {
            $query->whereDate('transaction_datetime', '>=', $search);
        })
        ->when($request->date_to, function($query, $search) {
            $query->whereDate('transaction_datetime', '<=', $search);
        })
        ->when($sortKey, function($query, $search) use ($sortBy) {
            $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
        });

        return $query;
    }

    public function scopeFilterReport($query, $request)
    {
        $query->when($request->codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
            }else {
                $search = [$search];
            }
            $query->whereHas('vend', function($query) use ($search) {
                $query->whereIn('code', $search);
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
        ->when($request->is_binded_customer, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->has('vend.latestVendBinding');
                }else {
                    $query->doesntHave('vend.latestVendBinding');
                }
            }
        })
        ->when($request->location_type_id, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('vend.latestVendBinding.customer', function($query) use ($search) {
                    $query->where('location_type_id', $search);
                });
            }
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('vend.operators', function($query) use ($search) {
                    $query->where('operators.id', $search);
                });
            }
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
        ->whereIn('vend_transaction_json->SErr', [0, 6]);

        return $query;
    }

    public function scopeIsSuccessful($query)
    {
        return $query->whereIn('vend_transaction_json->SErr', [0, 6]);
    }

    public function scopeIsFailure($query)
    {
        return $query->whereNotIn('vend_transaction_json->SErr', [0, 6]);
    }
}
