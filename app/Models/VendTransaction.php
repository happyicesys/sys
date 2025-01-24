<?php

namespace App\Models;

use App\Models\Scopes\OperatorTransactionFilterScope;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GetUserTimezone;

class VendTransaction extends Model
{
    use GetUserTimezone, HasFactory;

    const INTERFACE_TYPE_0 = 0;
    const INTERFACE_TYPE_1 = 1;

    const INTERFACE_TYPE_MAPPINGS = [
        self::INTERFACE_TYPE_0 => 'Normal',
        self::INTERFACE_TYPE_1 => 'Soft Keyboard/ Multiple Cart',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new OperatorTransactionFilterScope);
    }

    protected $casts = [
        'items_json' => 'json',
        'location_type_json' => 'json',
        'meta_json' => 'json',
        'transaction_datetime' => 'datetime',
        'vend_transaction_json' => 'json',
        'vend_transaction_items_json' => 'json',
    ];

    protected $fillable = [
        'customer_id',
        'order_id',
        'transaction_datetime',
        'amount',
        'gross_profit',
        'gross_profit_margin',
        'gst_vat_rate',
        'interface_type',
        'is_multiple',
        'is_payment_received',
        'is_refunded',
        'items_json',
        'location_type_id',
        'location_type_json',
        'meta_json',
        'operator_id',
        'payment_gateway_log_id',
        'payment_method_id',
        'product_id',
        'revenue',
        'vend_channel_id',
        'vend_channel_code',
        'vend_channel_error_id',
        'vend_id',
        'vend_transaction_json',
        'vend_transaction_items_json',
        'unit_cost',
        'unit_cost_id',
    ];

    protected $with = [
        'unitCost',
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
        return $this->unitCost ? $this->unitCost->cost * 100 : 0;
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

    public function vendChannelErrorLogs()
    {
        return $this->hasMany(VendChannelErrorLog::class)->latest();
    }

    public function vendTransactionItems()
    {
        return $this->hasMany(VendTransactionItem::class);
    }

    // scopes
    public function scopeFilterTransactionIndex($query, $request)
    {
        $isPaymentReceived = $request->is_payment_received != null ? $request->is_payment_received : 'all';

        $query = $query->when($request->date_from, function($query, $search) {
            $query->where('vend_transactions.transaction_datetime', '>=', $search);
        })
        ->when($request->date_to, function($query, $search) {
            $query->where('vend_transactions.transaction_datetime', '<=', $search);
        });
        // dd($request->all(), $query->toSql(), $query->getBindings());

        $query->when($request->has('visited'), function($query, $search) use ($request) {
            if($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            }else {
                $query->whereRaw('1 = 0');
            }
        })
        ->when($request->apk_ver, function($query, $search) {
            $query->where('vend_transactions.meta_json->apk_ver', 'LIKE', "%{$search}%");
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
            $query->where(function($query) use ($search) {
                $query->whereIn('vend_channel_code', $search)
                    ->orWhereHas('vendTransactionItems', function($query) use ($search) {
                        $query->whereIn('vend_channel_code', $search);
                    });
            });
        })
        ->when($request->errors, function($query, $search) {
            if(in_array('errors_only', $search)) {
                $query->where(function($query) {
                    $query->has('vendChannelError')
                        ->orWhereHas('vendTransactionItems.vendChannelError');
                });
            }else if(in_array('1', $search)) {
                $query->where(function($query) {
                    $query->whereHas('vendChannelError', function($query) {
                        $query->where('id', 1);
                    })->orWhereDoesntHave('vendChannelError');
                });
            }else {
                $query->where(function($query) use ($search) {
                    $query->whereHas('vendChannelError', function($query) use ($search) {
                        $query->whereIn('id', $search);
                    });

                    $query->orWhereHas('vendTransactionItems.vendChannelError', function ($query) use ($search) {
                        $query->whereIn('id', $search);
                    });
                });
            }
        })
        ->when($request->has('interface_type'), function($query, $search) use ($request) {
            if($request->interface_type != 'all') {
                $query->where('interface_type', $request->interface_type);
            }
        })
        // ->where(function($query) use ($request) {
        //     if($request->interface_type != 'all') {
        //         $query->where('interface_type', $request->interface_type);
        //     }
        // })
        ->when($request->is_binded_customer, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->has('vend.customer');
                }else {
                    $query->doesntHave('vend.customer');
                }
            }
        })
        ->when($request->is_multiple, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->where('is_multiple', true);
                }else {
                    $query->where('is_multiple', false);
                }
            }
        })
        ->when($request->is_member, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->where('vend_transactions.vend_transaction_json->dcvend_user_id', '>', 0);
                }else {
                    $query->where(function($query) {
                        $query->where('vend_transactions.vend_transaction_json->dcvend_user_id', null)
                            ->orWhere('vend_transactions.vend_transaction_json->dcvend_user_id', 0);
                    });
                }
            }
        })
        ->when($request->is_refunded, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->where('is_refunded', true);
                }else {
                    $query->where('is_refunded', false);
                }
            }
        })
        ->when($isPaymentReceived, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->where(function($query) {
                        $query->where('is_payment_received', true)
                            ->orWhereHas('vendTransactionItems.vendChannelError', function($query) {
                                $query->whereIn('code', [0, 6]);
                            });
                    });
                    // $query->where('is_payment_received', true);
                }else {
                    $query->where(function($query) {
                        $query->where('is_payment_received', false)
                            ->orWhereHas('vendTransactionItems.vendChannelError', function($query) {
                                $query->whereNotIn('code', [0, 6]);
                            });
                    });
                }
            }
        })
        ->when($request->order_id, function($query, $search) {
            $query->where('vend_transactions.order_id', 'LIKE', "%{$search}%");
        })
        ->when($request->paymentMethod, function($query, $search) {
            $query->where('payment_method_id', $search);
        })
        ->when($request->categories, function($query, $search) {
            $query->whereIn('vend_transactions.customer_id', function($query) use ($search) {
                $query->select('id')->from('customers')->whereIn('category_id', $search);
            });
        })
        ->when($request->categoryGroups, function($query, $search) {
            $query->whereIn('vend_transactions.customer_id', function($query) use ($search) {
                $query->select('id')->from('customers')->whereIn('category_id', function($query) use ($search) {
                    $query->select('id')->from('categories')->whereIn('category_group_id', $search);
                });
            });
        })
        ->when($request->customer, function($query, $search) {
            if(strpos($search, "-")) {
                $searchArray = explode("-", $search);
                    $query->whereIn('vend_transactions.customer_id', function($query) use ($searchArray) {
                        $query->select('id')->from('customers')
                            ->where('virtual_customer_prefix', $searchArray[0])
                            ->where('virtual_customer_code', $searchArray[1]);
                    });
            }else {
                $query->whereIn('vend_transactions.customer_id', function($query) use ($search) {
                    $query->select('id')->from('customers')->where(function($query) use ($search) {
                        $query->where('virtual_customer_prefix', 'LIKE', "{$search}%")
                            ->orWhere('virtual_customer_code', 'LIKE', "{$search}%")
                            ->orWhere(DB::raw("lower(name)"), 'LIKE', '%'.strtolower( $search ).'%');
                    });
                });
            }
        })
        ->when($request->location_type_id, function($query, $search) {
            if($search != 'all') {
                $query->whereIn('vend_transactions.customer_id', function($query) use ($search) {
                    $query->select('id')->from('customers')->where('location_type_id', $search);
                });
            }
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
                $query->where('vend_transactions.operator_id', $search);
            }
        })
        ->when($request->operators, function($query, $search) {
            if(!in_array('all', $search)){
                $query->whereIn('vend_transactions.operator_id', $search);
            }
        })
        ->when($request->product_code, function($query, $search) {
            $query->where(function($query) use ($search) {
                $query->whereIn('vend_transactions.product_id', function($query) use ($search) {
                    $query->select('id')->from('products')->where('code', 'LIKE', "{$search}%");
                });
                $query->orWhereHas('vendTransactionItems', function($query) use ($search) {
                    $query->whereIn('product_id', function($query) use ($search) {
                        $query->select('id')->from('products')->where('code', 'LIKE', "{$search}%");
                    });
                });
            });
        })
        ->when($request->product_name, function($query, $search) {
            $query->where(function($query) use ($search) {
                $query->whereIn('vend_transactions.product_id', function($query) use ($search) {
                    $query->select('id')->from('products')->where('name', 'LIKE', "%{$search}%");
                });
                $query->orWhereHas('vendTransactionItems', function($query) use ($search) {
                    $query->whereIn('product_id', function($query) use ($search) {
                        $query->select('id')->from('products')->where('name', 'LIKE', "%{$search}%");
                    });
                });
            });
        })
        ->when($request->vendPrefixes, function($query, $search) {
            if(!in_array('all', $search)){
                $query->whereHas('vend', function($query) use ($search) {
                    if(in_array('single-ud', $search)) {
                        $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                        unset($search[array_search('single-ud', $search)]);
                    }
                    $query->whereIn('vend_prefix_id', $search);
                });
            }
        })
        ->when($request->sortKey, function($query, $search) use ($request) {
            if(strpos($search, '->')) {
                $inputSearch = explode("->", $search);
                $query->orderByRaw('LENGTH(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")))'.(filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                ->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }else {
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }
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
            $query->whereIn('customer_id', function($query) use ($search) {
                $query->select('id')->from('customers')->where('code', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->customer_name, function($query, $search) {
            $query->where(function($query) use ($search) {
                $query->whereIn('vend_transactions.customer_id', function($query) use ($search) {
                    $query->select('id')->from('customers')->where('name', 'LIKE', "{$search}%");
                });
                $query->orWhereIn('vend_transactions.vend_id', function($query) use ($search) {
                    $query->select('id')->from('vends')->where('name', 'LIKE', "{$search}%");
                });
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
        ->when($request->is_multiple, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->where('is_multiple', true);
                }else {
                    $query->where('is_multiple', false);
                }
            }
        })
        ->when($request->is_refunded, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->where('is_refunded', true);
                }else {
                    $query->where('is_refunded', false);
                }
            }
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
                $query->where('operator_id', $search);
            }
        })
        ->when($request->categories, function($query, $search) {
            $query->whereHas('vend.customer.category', function($query) use ($search) {
                $query->whereIn('id', $search);
            });
        })
        ->when($request->categoryGroups, function($query, $search) {
            $query->whereHas('vend.customer.category.categoryGroup', function($query) use ($search) {
                $query->whereIn('id', $search);
            });
        })
        ->when($request->vendPrefixes, function($query, $search) {
            if(!in_array('all', $search)){
                $query->whereHas('vend', function($query) use ($search) {
                    if(in_array('single-ud', $search)) {
                        $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                        unset($search[array_search('single-ud', $search)]);
                    }
                    $query->whereIn('vend_prefix_id', $search);
                });
            }
        });

        return $query;
    }

    public function scopeIsSuccessful($query)
    {
        return $query->where(function($query) {
            $query->where('vend_transaction_json->SErr', 0)
            ->orWhere('vend_transaction_json->SErr', 6)
            ->orWhere('vend_transaction_json->GET_TYPE', 1);
        });
    }

    public function scopeIsFailure($query)
    {
        return $query->where(function($query) {
            $query->whereNotIn('vend_transaction_json->SErr', [0, 6])
                ->orWhereNot('vend_transaction_json->GET_TYPE', 1);
        });
    }

    public function scopeIsError($query)
    {
        return $query->where(function($query) {
            $query->whereNot('vend_transaction_json->SErr', 0)
                ->orWhereNot('vend_transaction_json->GET_TYPE', 1);
        });
    }
}
