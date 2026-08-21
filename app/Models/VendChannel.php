<?php

namespace App\Models;

use App\Models\OpsJob;
use App\Events\VendChannelSaved;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendChannel extends Model
{
    use HasFactory;

    /*
     * ==================================================================
     * DO NOT add a global "Access Product(s)" scope to this model.
     * ==================================================================
     * VendChannel is read on machine-facing and PUBLIC paths, notably
     * RefundFormController::machineProducts() (unauthenticated, throttle only)
     * - see the comment at RefundFormController:114-124, which already had to
     * strip Product's scope for exactly this reason but does NOT strip anything
     * from its VendChannel::query(). A global scope here would break the
     * customer-facing refund form for any request made while an admin session
     * cookie happens to be present, and risks handing a vending machine a
     * partial planogram.
     *
     * Use the opt-in scopeVisibleToProductAccess() below on UI read surfaces
     * instead, so every filtered call site stays explicit and greppable.
     */

    protected $fillable = [
        'amount',
        'amount2',
        'capacity',
        'code',
        'discount_group',
        'error_rate_json',
        'is_active',
        'locked_qty',
        'product_id',
        'qty_not_available_duration',
        'qty_sold_at',
        'qty_restocked_at',
        'sku_code',
        'qty',
        'vend_id',
    ];

    protected $casts = [
        'error_rate_json' => 'json',
        'qty_restocked_at' => 'datetime',
        'qty_sold_at' => 'datetime',
    ];

    /**
     * Opt-in "Access Product(s)" narrowing for UI surfaces that list channels.
     *
     * Channels with a NULL product_id (empty / unmapped slots) are excluded:
     * an unmapped slot's stock is not the restricted viewer's to see.
     *
     * @param  array<int, int>|null  $ids  omit to resolve from the session
     */
    public function scopeVisibleToProductAccess($query, ?array $ids = null)
    {
        return \App\Support\ProductAccess::applyToColumn(
            $query,
            $this->getTable() . '.product_id',
            func_num_args() > 1 ? $ids : \App\Support\ProductAccess::current()
        );
    }

    // relationships
    public function deliveryProductMappingVendChannels()
    {
        return $this->hasMany(DeliveryProductMappingVendChannel::class);
    }

    public function latestOpsJobItemChannel()
    {
        return $this->hasOne(OpsJobItemChannel::class)->whereHas('opsJobItem', function ($query) {
            $query->where('status', '>=', OpsJob::STATUS_DELIVERED)
                ->where('status', '<>', OpsJob::STATUS_CANCELLED);
        })->orderByDesc('created_at');
    }

    public function opsJobItemChannels()
    {
        return $this->hasMany(OpsJobItemChannel::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendChannelErrorLogs()
    {
        return $this->hasMany(VendChannelErrorLog::class)->latest();
    }

    public function vendChannelLatestError()
    {
        // latestOfMany() resolves the newest error log per channel via an efficient
        // subquery. The old hasOne()->orderByDesc('created_at') had no limit, so on
        // EAGER load it fetched EVERY error log for all channels (ordered) and kept
        // the first per channel in PHP — ~400ms for 14 channels with long error
        // history (DeliveryPlatformService). Result is identical: the latest error.
        return $this->hasOne(VendChannelErrorLog::class)->latestOfMany('created_at');
    }

    public function vendTransactions()
    {
        return $this->hasMany(VendTransaction::class);
    }

    public function vendTransactionItems()
    {
        return $this->hasMany(VendTransactionItem::class);
    }

    // custom functions
    public function daysVendTransactions($from = 0, $to = 0)
    {
        return $this->vendTransactions()
            // ->isSuccessful()
            ->where('transaction_datetime', '>=', Carbon::today()->subDays($from)->startOfDay())
            ->where('transaction_datetime', '<=', Carbon::today()->subDays($to)->endOfDay());
    }

    public function vendTwoDaysErrorTransactions()
    {
        return $this->daysVendTransactions(1, 0)->isError()->latest();
    }

    public function vendSevenDaysErrorTransactions()
    {
        return $this->daysVendTransactions(6, 0)->isError()->latest();
    }

    // attributes
    public function getServerAmountAttribute()
    {
        if (!$this->vend_id || !$this->product_id) {
            return null;
        }

        // The tier is the SITE's (customers.selling_price_type); the machine
        // only says whether it follows it — see Vend::serverPriceType(). Raw
        // query on purpose: avoid lazy-loading the full Vend model (SELECT *
        // fetches all JSON columns) just to read two tiny columns.
        $pricing = DB::table('vends')
            ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
            ->where('vends.id', $this->vend_id)
            ->select('vends.is_using_server_price', 'customers.selling_price_type')
            ->first();

        if (! $pricing || ! $pricing->is_using_server_price || ! $pricing->selling_price_type) {
            return null;
        }

        return DB::table('selling_prices')
            ->where('product_id', $this->product_id)
            ->where('type', $pricing->selling_price_type)
            ->value('amount');
    }


    // scopes
    public function scopeFilterIndex($query, $request)
    {
        $isDoorOpen = $request->is_door_open != null ? $request->is_door_open : 'all';
        $isOnline = $request->is_online != null ? $request->is_online : 'all';
        $isSensor = $request->is_sensor != null ? $request->is_sensor : 'all';
        $isBindedCustomer = $request->is_binded_customer != null ? $request->is_binded_customer : 'true';
        $isBindedCustomer = auth()->user()->hasRole('operator') ? 'all' : $isBindedCustomer;
        $sortKey = $request->sortKey ? $request->sortKey : 'vends.is_online';
        $sortBy = $request->sortBy ? $request->sortBy : false;

        return $query->when($request->codes, function ($query, $search) {
            if (strpos($search, ',') !== false) {
                $search = explode(',', $search);
            } else {
                $search = [$search];
            }
            $query->whereHas('vend', function ($query) use ($search) {
                $query->whereIn('vends.code', $search);
            });
        })
            ->when($request->channel_codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = explode(',', $search);
                } else {
                    $search = [$search];
                }
                $query->whereIn('vend_channels.code', $search);
            })
            ->when($request->serialNum, function ($query, $search) {
                $query->whereHas('vend', function ($query) use ($search) {
                    $query->where('serial_num', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->customer_code, function ($query, $search) {
                $query->whereHas('vend.customer', function ($query) use ($search) {
                    $query->where('customers.code', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->customer_name, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('vend.customer', function ($query) use ($search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                        ->orWhere('vends.name', 'LIKE', "%{$search}%");
                });
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
            ->when($request->fan_rpm !== null && $request->fan_rpm !== '' && $request->fan_rpm !== 'all', function ($query) use ($request) {
                $search = $request->fan_rpm;
                $query->whereHas('vend', function ($query) use ($search) {
                    if ($search == '0') {
                        $query->where('is_fan_enabled', true)->where('parameter_json->fan', 0);
                    } else if ($search == '>0') {
                        $query->where('is_fan_enabled', true)->where('parameter_json->fan', '>', 0);
                    } else if ($search == 'N/A') {
                        $query->where('is_fan_enabled', false);
                    } else if ($search == '--') {
                        $query->where('is_fan_enabled', true)->where(function ($q) {
                            $q->whereNull('parameter_json->fan');
                        });
                    }
                });
            })
            ->when($isDoorOpen, function ($query, $search) {
                if ($search != 'all') {
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->where('parameter_json->door', '=', $search);
                    });
                }
            })
            ->when($isBindedCustomer, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->has('vend.customer');
                    } else {
                        $query->doesntHave('vend.customer');
                    }
                }
            })
            ->when($request->tempHigherThan, function ($query, $search) {
                if (is_numeric($search)) {
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->where('temp', '>=', $search * 10);
                    });
                }
            })
            ->when($request->tempDeltaHigherThan, function ($query, $search) {
                if (is_numeric($search)) {
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->whereNotNull('parameter_json->t2')
                            ->where('parameter_json->t2', '!=', VendTemp::TEMPERATURE_ERROR)
                            ->whereRaw('temp - json_extract(parameter_json, "$.t2") > ?', [$search * 10]);
                    });
                }
            })
            ->when($request->errors, function ($query, $search) {
                if (in_array('errors_only', $search)) {
                    $query->whereHas('vendChannelErrorLogs', function ($query) {
                        $query->where('is_error_cleared', false);
                    });
                } else {
                    $query->whereHas('vendChannelErrorLogs', function ($query) use ($search) {
                        $query->whereIn('vend_channel_error_id', $search)->where('is_error_cleared', false);
                    });
                }

            })
            ->when($request->operator_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->where('operator_id', $search);
                    });
                }
            })
            ->when($isOnline, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $search = true;
                    } else {
                        $search = false;
                    }
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->where('is_online', $search);
                    });
                }
            })
            ->when($isSensor, function ($query, $search) {
                if ($search != 'all') {
                    $query->whereHas('vend', function ($query) use ($search) {
                        if ($search == 'true') {
                            $query->whereIn('parameter_json->Sensor', ['1', '3', '5', '7', '9']);
                        } else {
                            $query->whereIn('parameter_json->Sensor', ['0', '2', '4', '6', '8', '10']);
                        }
                    });
                }
            })
            ->when($sortKey, function ($query, $search) use ($sortBy) {
                $query->whereHas('vend', function ($query) use ($search, $sortBy) {
                    if (strpos($search, '->')) {
                        $inputSearch = explode("->", $search);
                        // C3: whitelist identifier chars before raw interpolation (no-op for valid sort keys)
                        $inputSearch[0] = preg_replace('/[^A-Za-z0-9_]/', '', $inputSearch[0] ?? '');
                        $inputSearch[1] = preg_replace('/[^A-Za-z0-9_]/', '', $inputSearch[1] ?? '');
                        $query->orderByRaw('LENGTH(json_unquote(json_extract(`' . $inputSearch[0] . '`, "$.' . $inputSearch[1] . '")))' . (filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                            ->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    } else {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    }
                });

            });
    }
}
