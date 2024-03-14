<?php

namespace App\Models;

use App\Events\VendChannelSaved;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'amount2',
        'capacity',
        'code',
        'discount_group',
        'is_active',
        'locked_qty',
        'product_id',
        'sku_code',
        'qty',
        'last_inactive_at',
        'last_active_at',
        'inactive_active_duration',
        'vend_id',
    ];

    protected $casts = [
        'last_inactive_at' => 'datetime',
        'last_active_at' => 'datetime',
    ];

    // relationships
    public function deliveryProductMappingVendChannels()
    {
        return $this->hasMany(DeliveryProductMappingVendChannel::class);
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
        return $this->hasOne(VendChannelErrorLog::class)->orderByDesc('created_at');
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

        return $query->when($request->codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
            }else {
                $search = [$search];
            }
            $query->whereHas('vend', function($query) use ($search) {
                $query->whereIn('vends.code', $search);
            });
        })
        ->when($request->channel_codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
            }else {
                $search = [$search];
            }
            $query->whereIn('vend_channels.code', $search);
        })
        ->when($request->serialNum, function($query, $search) {
            $query->whereHas('vend', function($query) use ($search) {
                $query->where('serial_num', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->customer_code, function($query, $search) {
            $query->whereHas('vend.customer', function($query) use ($search) {
                $query->where('customers.code', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->customer_name, function($query, $search) {
            $query->where(function($query) use ($search) {
                $query->whereHas('vend.customer', function($query) use ($search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhere('vends.name', 'LIKE', "%{$search}%");
            });
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
        ->when($request->fanSpeedLowerThan, function($query, $search) {
            if(is_numeric($search)) {
                $query->whereHas('vend', function($query) use ($search) {
                    $query->where('parameter_json->fan', '<=', $search)->where('parameter_json->fan', '>', 0);
                });
            }
        })
        ->when($isDoorOpen, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('vend', function($query) use ($search) {
                    $query->where('parameter_json->door', '=', $search);
                });
            }
        })
        ->when($isBindedCustomer, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->has('vend.customer');
                }else {
                    $query->doesntHave('vend.customer');
                }
            }
        })
        ->when($request->tempHigherThan, function($query, $search) {
            if(is_numeric($search)) {
                $query->whereHas('vend', function($query) use ($search) {
                    $query->where('temp', '>=', $search * 10);
                });
            }
        })
        ->when($request->tempDeltaHigherThan, function($query, $search) {
            if(is_numeric($search)) {
                $query->whereHas('vend', function($query) use ($search) {
                    $query->whereNotNull('parameter_json->t2')
                        ->where('parameter_json->t2', '!=', VendTemp::TEMPERATURE_ERROR)
                        ->whereRaw('temp - json_extract(parameter_json, "$.t2") > ?', [$search * 10]);
                });
            }
        })
        ->when($request->errors, function($query, $search) {
            if(in_array('errors_only', $search)) {
                $query->whereHas('vendChannelErrorLogs', function($query) {
                    $query->where('is_error_cleared', false);
                });
            }else {
                $query->whereHas('vendChannelErrorLogs', function($query) use ($search) {
                    $query->whereIn('vend_channel_error_id', $search)->where('is_error_cleared', false);
                });
            }

        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('vend.operators', function($query) use ($search) {
                    $query->where('operator_id', $search);
                });
            }
        })
        ->when($isOnline, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $search = true;
                }else {
                    $search = false;
                }
                $query->whereHas('vend', function($query) use ($search) {
                    $query->where('is_online', $search);
                });
            }
        })
        ->when($isSensor, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('vend', function($query) use ($search) {
                    if($search == 'true') {
                        $query->whereIn('parameter_json->Sensor', ['1', '3', '5', '7', '9']);
                    }else {
                        $query->whereIn('parameter_json->Sensor', ['0', '2', '4', '6', '8', '10']);
                    }
                });
            }
        })
        ->when($sortKey, function($query, $search) use ($sortBy) {
            $query->whereHas('vend', function($query) use ($search, $sortBy) {
                if(strpos($search, '->')) {
                    $inputSearch = explode("->", $search);
                    $query->orderByRaw('LENGTH(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")))'.(filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                    ->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                }else {
                    $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                }
            });

        });
    }
}
