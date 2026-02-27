<?php

namespace App\Models;

use App\Models\Scopes\OperatorTransactionFilterScope;
use App\Models\Scopes\OperatorUserTransactionFilterScope;
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
        static::addGlobalScope(new OperatorUserTransactionFilterScope);
    }

    protected $casts = [
        'items_json' => 'json',
        'label_json' => 'json',
        'location_type_json' => 'json',
        'meta_json' => 'json',
        'transaction_datetime' => 'datetime',
        'vend_transaction_json' => 'json',
        'is_zero_amount' => 'boolean',
    ];

    protected $fillable = [
        'customer_id',
        'order_id',
        'transaction_datetime',
        'amount',
        'is_zero_amount',
        'gross_profit',
        'gross_profit_margin',
        'gst_vat_rate',
        'interface_type',
        'is_multiple',
        'is_payment_received',
        'is_refunded',
        'items_json',
        'label_json',
        'location_type_id',
        'location_type_json',
        'meta_json',
        'operator_id',
        'payment_gateway_log_id',
        'payment_method_id',
        'product_id',
        'qty',
        'revenue',
        'success_qty',
        'dispensed_qty',
        'vend_channel_id',
        'vend_channel_code',
        'vend_channel_error_id',
        'vend_contract_id',
        'vend_model_id',
        'vend_prefix_id',
        'vend_id',
        'vend_transaction_json',
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
        return $this->amount / (1.00 + ($this->gst_vat_rate / 100));
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
    public function scopeFilterTransactionIndex($query, $request, $skipSort = false)
    {
        $isPaymentReceived = $request->is_payment_received != null ? $request->is_payment_received : 'all';

        $query = $query->when($request->date_from, function ($query, $search) {
            $query->where('vend_transactions.transaction_datetime', '>=', $search);
        })
            ->when($request->date_to, function ($query, $search) {
                $query->where('vend_transactions.transaction_datetime', '<=', $search);
            });

        $query->when($request->has('visited'), function ($query, $search) use ($request) {
            if ($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            } else {
                $query->whereRaw('1 = 0');
            }
        })
            ->when($request->apk_ver, function ($query, $search) {
                $query->where('vend_transactions.meta_json->apk_ver', 'LIKE', "%{$search}%");
            })
            ->when($request->codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = array_map('trim', explode(',', $search));
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->whereIn('code', $search);
                    });
                } else {
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->where('code', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->when($request->channel_codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = array_map('trim', explode(',', $search));
                } else {
                    $search = [$search];
                }
                $query->where(function ($query) use ($search) {
                    $query->whereIn('vend_transactions.vend_channel_code', $search)
                        ->orWhereHas('vendTransactionItems', function ($query) use ($search) {
                            $query->whereIn('vend_channel_code', $search);
                        });
                });
            })
            ->when($request->errors, function ($query, $search) {
                if (in_array('errors_only', $search)) {
                    $query->where(function ($query) {
                        $query->has('vendChannelError')
                            ->orWhereHas('vendTransactionItems.vendChannelError');
                    });
                } else if (in_array('1', $search)) {
                    $query->where(function ($query) {
                        $query->whereHas('vendChannelError', function ($query) {
                            $query->where('id', 1);
                        })->orWhereDoesntHave('vendChannelError');
                    });
                } else {
                    $query->where(function ($query) use ($search) {
                        $query->whereHas('vendChannelError', function ($query) use ($search) {
                            $query->whereIn('id', $search);
                        });

                        $query->orWhereHas('vendTransactionItems.vendChannelError', function ($query) use ($search) {
                            $query->whereIn('id', $search);
                        });
                    });
                }
            })
            ->when($request->hid_card_id, function ($query, $search) {
                $query->where('vend_transactions.meta_json->hid_card_id', 'LIKE', "%{$search}%");
            })
            ->when($request->has('interface_type'), function ($query, $search) use ($request) {
                if ($request->interface_type != 'all') {
                    $query->where('vend_transactions.interface_type', $request->interface_type);
                }
            })
            // ->where(function($query) use ($request) {
            //     if($request->interface_type != 'all') {
            //         $query->where('interface_type', $request->interface_type);
            //     }
            // })
            ->when($request->is_binded_customer, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->has('vend.customer');
                    } else {
                        $query->doesntHave('vend.customer');
                    }
                }
            })
            ->when($request->is_multiple, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->where('vend_transactions.is_multiple', true);
                    } else {
                        $query->where('vend_transactions.is_multiple', false);
                    }
                }
            })
            ->when($request->is_member, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->where('vend_transactions.vend_transaction_json->dcvend_user_id', '>', 0);
                    } else {
                        $query->where(function ($query) {
                            $query->where('vend_transactions.vend_transaction_json->dcvend_user_id', null)
                                ->orWhere('vend_transactions.vend_transaction_json->dcvend_user_id', 0);
                        });
                    }
                }
            })
            ->when($request->is_refunded, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->where('vend_transactions.is_refunded', true);
                    } else {
                        $query->where('vend_transactions.is_refunded', false);
                    }
                }
            })
            ->when($request->is_voucher, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->whereNotNull('meta_json->vouchers');
                    } else {
                        $query->whereNull('meta_json->vouchers');
                    }
                }
            })
            ->when($isPaymentReceived, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->where(function ($query) {
                            $query->where('vend_transactions.is_payment_received', true)
                                ->orWhereHas('vendTransactionItems.vendChannelError', function ($query) {
                                    $query->whereIn('code', [0, 6]);
                                });
                        });
                        // $query->where('is_payment_received', true);
                    } else {
                        $query->where(function ($query) {
                            $query->where('vend_transactions.is_payment_received', false)
                                ->orWhereHas('vendTransactionItems.vendChannelError', function ($query) {
                                    $query->whereNotIn('code', [0, 6]);
                                });
                        });
                    }
                }
            })
            ->when($request->member_code, function ($query, $search) {
                $query->where('vend_transactions.vend_transaction_json->dcvend_user_id', $search);
            })
            ->when($request->order_id, function ($query, $search) {
                $query->where('vend_transactions.order_id', 'LIKE', "%{$search}%");
            })
            ->when($request->paymentMethods, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('vend_transactions.payment_method_id', $search);
                }
            })
            ->when($request->categories, function ($query, $search) {
                $query->whereIn('vend_transactions.customer_id', function ($query) use ($search) {
                    $query->select('id')->from('customers')->whereIn('category_id', $search);
                });
            })
            ->when($request->categoryGroups, function ($query, $search) {
                $query->whereIn('vend_transactions.customer_id', function ($query) use ($search) {
                    $query->select('id')->from('customers')->whereIn('category_id', function ($query) use ($search) {
                        $query->select('id')->from('categories')->whereIn('category_group_id', $search);
                    });
                });
            })
            ->when($request->customer, function ($query, $search) {
                if (strpos($search, "-")) {
                    $searchArray = explode("-", $search);
                    $query->whereIn('vend_transactions.customer_id', function ($query) use ($searchArray) {
                        $query->select('id')->from('customers')
                            ->where('virtual_customer_prefix', $searchArray[0])
                            ->where('virtual_customer_code', $searchArray[1]);
                    });
                } else {
                    $query->whereIn('vend_transactions.customer_id', function ($query) use ($search) {
                        $query->select('id')->from('customers')->where(function ($query) use ($search) {
                            $query->where('virtual_customer_prefix', 'LIKE', "{$search}%")
                                ->orWhere('virtual_customer_code', 'LIKE', "{$search}%")
                                ->orWhere(DB::raw("lower(name)"), 'LIKE', '%' . strtolower($search) . '%');
                        });
                    });
                }
            })
            ->when($request->location_type_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vend_transactions.location_type_id', $search);
                    // $query->whereIn('vend_transactions.customer_id', function($query) use ($search) {
                    //     $query->select('id')->from('customers')->where('location_type_id', $search);
                    // });
                }
            })
            ->when($request->operator_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vend_transactions.operator_id', $search);
                }
            })
            ->when($request->operators, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('vend_transactions.operator_id', $search);
                }
            })
            ->when($request->product_code, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->whereIn('vend_transactions.product_id', function ($query) use ($search) {
                        $query->select('id')->from('products')->where('code', 'LIKE', "{$search}%");
                    });
                    $query->orWhereHas('vendTransactionItems', function ($query) use ($search) {
                        $query->whereIn('product_id', function ($query) use ($search) {
                            $query->select('id')->from('products')->where('code', 'LIKE', "{$search}%");
                        });
                    });
                });
            })
            ->when($request->product_name, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->whereIn('vend_transactions.product_id', function ($query) use ($search) {
                        $query->select('id')->from('products')->where('name', 'LIKE', "%{$search}%");
                    });
                    $query->orWhereHas('vendTransactionItems', function ($query) use ($search) {
                        $query->whereIn('product_id', function ($query) use ($search) {
                            $query->select('id')->from('products')->where('name', 'LIKE', "%{$search}%");
                        });
                    });
                });
            })
            ->when($request->filled('tag') && $request->tag !== 'all', function ($q) use ($request) {
                if ($request->tag === 'any') {
                    // any label at all
                    $q->whereRaw('JSON_LENGTH(COALESCE(vend_transactions.label_json, JSON_ARRAY())) > 0');
                } else {
                    // Match transactions whose label_json contains either the tag id, name, or slug
                    // Prefer simple JSON contains, then fall back to JSON_TABLE join for cross-representation matching
                    $tag = trim((string) $request->tag);
                    $isNumeric = is_numeric($tag);

                    $q->where(function ($sub) use ($isNumeric, $tag) {
                        if ($isNumeric) {
                            // Direct id stored in JSON array
                            $sub->whereJsonContains('vend_transactions.label_json', (int) $tag);
                        } else {
                            // Direct name/slug stored in JSON array
                            $sub->whereJsonContains('vend_transactions.label_json', $tag);
                        }

                        // Cross-match via tags table (handles when JSON stores name but filter is id, or vice versa)
                        if ($isNumeric) {
                            $where = "t.id = ?";
                            $bindings = [(int) $tag];
                        } else {
                            $where = "t.name = ? OR t.slug = ?";
                            $bindings = [$tag, $tag];
                        }

                        $sub->orWhereRaw(
                            "EXISTS (\n" .
                            "  SELECT 1\n" .
                            "  FROM JSON_TABLE(\n" .
                            "         COALESCE(vend_transactions.label_json, JSON_ARRAY()),\n" .
                            "         '$[*]' COLUMNS(\n" .
                            "           tag_id BIGINT PATH '$',\n" .
                            "           tag_name VARCHAR(255) PATH '$'\n" .
                            "         )\n" .
                            "       ) jt\n" .
                            "  JOIN tags t ON (t.id = jt.tag_id OR t.name = jt.tag_name)\n" .
                            "  WHERE {$where}\n" .
                            ")",
                            $bindings
                        );
                    });
                }
            })
            ->when($request->vendContracts, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('vend_transactions.vend_contract_id', $search);
                    // $query->whereHas('vend', function($query) use ($search) {
                    //     $query->whereIn('vend_contract_id', $search);
                    // });
                }
            })
            ->when($request->vendModels, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('vend_transactions.vend_model_id', $search);
                    // $query->whereHas('vend', function($query) use ($search) {
                    //     $query->whereIn('vend_model_id', $search);
                    // });
                }
            })
            ->when($request->vendPrefixes, function ($query, $search) {
                if (!in_array('all', $search)) {
                    if (in_array('single-ud', $search)) {
                        $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                        unset($search[array_search('single-ud', $search)]);
                    }
                    $query->whereIn('vend_transactions.vend_prefix_id', $search);
                    // $query->whereHas('vend', function($query) use ($search) {
                    //     if(in_array('single-ud', $search)) {
                    //         $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                    //         unset($search[array_search('single-ud', $search)]);
                    //     }
                    //     $query->whereIn('vend_prefix_id', $search);
                    // });
                }
            });

        if (!$skipSort) {
            $query->when($request->sortKey, function ($query, $search) use ($request) {
                if (strpos($search, '->')) {
                    $inputSearch = explode("->", $search);
                    $query->orderByRaw('LENGTH(json_unquote(json_extract(`' . $inputSearch[0] . '`, "$.' . $inputSearch[1] . '")))' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                        ->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                } else {
                    $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                }
            });
        }

        return $query;
    }

    public function scopeFilterReport($query, $request)
    {
        $query->when($request->has('visited'), function ($query, $search) use ($request) {
            if ($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            } else {
                $query->whereRaw('1 = 0');
            }
        })
            ->when($request->codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = explode(',', $search);
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->whereIn('code', $search);
                    });
                } else {
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->where('code', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->when($request->customer_code, function ($query, $search) {
                $query->whereIn('customer_id', function ($query) use ($search) {
                    $query->select('id')->from('customers')->where('code', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->customer_name, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->whereIn('vend_transactions.customer_id', function ($query) use ($search) {
                        $query->select('id')->from('customers')->where('name', 'LIKE', "{$search}%");
                    });
                    $query->orWhereIn('vend_transactions.vend_id', function ($query) use ($search) {
                        $query->select('id')->from('vends')->where('name', 'LIKE', "{$search}%");
                    });
                });
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
            ->when($request->is_multiple, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->where('is_multiple', true);
                    } else {
                        $query->where('is_multiple', false);
                    }
                }
            })
            ->when($request->is_refunded, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->where('vend_transactions.is_refunded', true);
                    } else {
                        $query->where('vend_transactions.is_refunded', false);
                    }
                }
            })
            ->when($request->location_type_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->whereIn('customer_id', function ($query) use ($search) {
                        $query->select('id')->from('customers')->where('location_type_id', $search);
                    });
                }
            })
            ->when($request->operator_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('operator_id', $search);
                }
            })
            ->when($request->categories, function ($query, $search) {
                $query->whereHas('vend.customer.category', function ($query) use ($search) {
                    $query->whereIn('id', $search);
                });
            })
            ->when($request->categoryGroups, function ($query, $search) {
                $query->whereHas('vend.customer.category.categoryGroup', function ($query) use ($search) {
                    $query->whereIn('id', $search);
                });
            })
            ->when($request->vendContracts, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->whereIn('vend_contract_id', $search);
                    });
                }
            })
            ->when($request->vendPrefixes, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereHas('vend', function ($query) use ($search) {
                        if (in_array('single-ud', $search)) {
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
        return $query->where(function ($query) {
            $query->whereIn('vend_channel_error_id', [1, 5])
                ->orWhereNull('vend_channel_error_id')
                ->orWhere('vend_transaction_json->GET_TYPE', 1);
        });
    }

    public function scopeIsFailure($query)
    {
        return $query->where(function ($query) {
            $query->whereNotNull('vend_channel_error_id')
                ->whereNotIn('vend_channel_error_id', [1, 5])
                ->orWhereNot('vend_transaction_json->GET_TYPE', 1);
        });
    }

    public function scopeIsError($query)
    {
        return $query->where(function ($query) {
            $query->whereNotNull('vend_channel_error_id')
                ->whereNotIn('vend_channel_error_id', [1, 5])
                ->orWhereNot('vend_transaction_json->GET_TYPE', 1);
        });
    }
}
