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
        'customer_json' => 'json',
        'items_json' => 'json',
        'location_type_json' => 'json',
        'operator_json' => 'json',
        'product_json' => 'json',
        'transaction_datetime' => 'datetime',
        'vend_json' => 'json',
        'vend_transaction_json' => 'json',
    ];

    protected $fillable = [
        'customer_id',
        'customer_json',
        'order_id',
        'transaction_datetime',
        'amount',
        'gross_profit',
        'gross_profit_margin',
        'gst_vat_rate',
        'is_multiple',
        'is_payment_received',
        'is_refunded',
        'items_json',
        'location_type_json',
        'operator_id',
        'operator_json',
        'payment_gateway_log_id',
        'payment_method_id',
        'product_id',
        'product_json',
        'revenue',
        'vend_channel_id',
        'vend_channel_code',
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
        return $this->amount/(1.00 + ($this->gst_vat_rate/100));
    }

    public function getUnitCost()
    {
        return $this->unitCost ? $this->unitCost->cost : 0;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function deliveryPlatformOrder()
    {
        return $this->hasOne(DeliveryPlatformOrder::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function paymentGatewayLog()
    {
        return $this->belongsTo(PaymentGatewayLog::class);
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
        $isPaymentReceived = $request->is_payment_received != null ? $request->is_payment_received : 'all';

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
                $query->whereHas('vend', function($query) use ($search) {
                    $query->whereIn('code', $search);
                });
            }else {
                $query->whereHas('vend', function($query) use ($search) {
                    $query->where('code', 'LIKE', "%{$search}%");
                });
            }
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
        ->when($request->is_binded_customer, function($query, $search) {
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
        ->when($request->order_id, function($query, $search) {
            $query->where('order_id', 'LIKE', "%{$search}%");
        })
        ->when($request->paymentMethod, function($query, $search) {
            $query->where('payment_method_id', $search);
        })
        // ->when($request->categories, function($query, $search) {
        //     $query->whereHas('vend.latestVendBinding.customer.category', function($query) use ($search) {
        //         $query->whereIn('id', $search);
        //     });
        // })
        // ->when($request->categoryGroups, function($query, $search) {
        //     $query->whereHas('vend.latestVendBinding.customer.category.categoryGroup', function($query) use ($search) {
        //         $query->whereIn('id', $search);
        //     });
        // })
        ->when($request->categories, function($query, $search) {
            $query->whereIn('customer_id', function($query) use ($search) {
                $query->select('id')->from('customers')->whereIn('category_id', $search);
            });
        })
        ->when($request->categoryGroups, function($query, $search) {
            $query->whereIn('customer_id', function($query) use ($search) {
                $query->select('id')->from('customers')->whereIn('category_id', function($query) use ($search) {
                    $query->select('id')->from('categories')->whereIn('category_group_id', $search);
                });
            });
        })
        ->when($request->customer_code, function($query, $search) {
            // $query->whereHas('customer', function($query) use ($search) {
            //     $query->where('code', 'LIKE', "{$search}%");
            // });
            $query->whereIn('customer_id', function($query) use ($search) {
                $query->select('id')->from('customers')->where('code', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->customer_name, function($query, $search) {
            // $query
            //     ->whereHas('customer', function($query) use ($search) {
            //         $query->where('name', 'LIKE', "{$search}%");
            //     })
            //     ->orWhereHas('vend', function($query) use ($search) {
            //         $query->where('name', 'LIKE', "{$search}%");
            //     });
            $query->where(function($query) use ($search) {
                $query->whereIn('customer_id', function($query) use ($search) {
                    $query->select('id')->from('customers')->where('name', 'LIKE', "{$search}%");
                });
                $query->orWhereIn('vend_id', function($query) use ($search) {
                    $query->select('id')->from('vends')->where('name', 'LIKE', "{$search}%");
                });
            });
        })
        ->when($request->location_type_id, function($query, $search) {
            if($search != 'all') {
                $query->whereIn('customer_id', function($query) use ($search) {
                    $query->select('id')->from('customers')->where('location_type_id', $search);
                });
                // $query->whereHas('vend.latestVendBinding.customer', function($query) use ($search) {
                //     $query->where('location_type_id', $search);
                // });
            }
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
                // $query->whereHas('vend.operators', function($query) use ($search) {
                //     $query->where('operators.id', $search);
                // });
                $query->where('operator_id', $search);
            }
        })
        ->when($request->product_code, function($query, $search) {
            $query->whereIn('product_id', function($query) use ($search) {
                $query->select('id')->from('products')->where('code', 'LIKE', "{$search}%");
            });
        })
        ->when($request->product_name, function($query, $search) {
            $query->whereIn('product_id', function($query) use ($search) {
                $query->select('id')->from('products')->where('name', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->date_from, function($query, $search) {
            $query->where('created_at', '>=', $search);
        })
        ->when($request->date_to, function($query, $search) {
            $query->where('created_at', '<=', $search);
        });

        return $query;
    }

    public function scopeFilterReport($query, $request)
    {
        $query->when($request->has('visited'), function($query, $search) use ($request) {
            if($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            }else {
                $query->whereRaw('1 = 0');
            }
        })
        ->when($request->codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
                $query->whereHas('vend', function($query) use ($search) {
                    $query->whereIn('code', $search);
                });
            }else {
                $query->whereHas('vend', function($query) use ($search) {
                    $query->where('code', 'LIKE', "%{$search}%");
                });
            }
        })
        ->when($request->customer_code, function($query, $search) {
            // $query->whereHas('customer', function($query) use ($search) {
            //     $query->where('code', 'LIKE', "{$search}%");
            // });
            $query->whereIn('customer_id', function($query) use ($search) {
                $query->select('id')->from('customers')->where('code', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->customer_name, function($query, $search) {
            // $query
            //     ->whereHas('customer', function($query) use ($search) {
            //         $query->where('name', 'LIKE', "{$search}%");
            //     })
            //     ->orWhereHas('vend', function($query) use ($search) {
            //         $query->where('name', 'LIKE', "{$search}%");
            //     });
            $query->where(function($query) use ($search) {
                $query->whereIn('customer_id', function($query) use ($search) {
                    $query->select('id')->from('customers')->where('name', 'LIKE', "{$search}%");
                });
                $query->orWhereIn('vend_id', function($query) use ($search) {
                    $query->select('id')->from('vends')->where('name', 'LIKE', "{$search}%");
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
                $query->whereIn('customer_id', function($query) use ($search) {
                    $query->select('id')->from('customers')->where('location_type_id', $search);
                });
                // $query->whereHas('vend.latestVendBinding.customer', function($query) use ($search) {
                //     $query->where('location_type_id', $search);
                // });
            }
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
                // $query->whereHas('vend.operators', function($query) use ($search) {
                //     $query->where('operators.id', $search);
                // });
                $query->where('operator_id', $search);
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
        });

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
