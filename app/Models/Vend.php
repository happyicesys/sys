<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vend extends Model
{
    use HasFactory;

    protected $casts = [
        'last_updated_at' => 'datetime',
        'parameter_json' => 'json',
        'temp_updated_at' => 'datetime',
        'vend_channel_error_logs_json' => 'json',
        'vend_channels_json' => 'json',
        'vend_channel_totals_json' => 'json',
    ];

    protected $fillable = [
        'code',
        'serial_num',
        'name',
        'temp',
        'temp_updated_at',
        'coin_amount',
        'firmware_ver',
        'is_door_open',
        'is_offline_notification_sent',
        'is_online',
        'is_sensor_normal',
        'is_temp_error',
        'last_updated_at',
        'parameter_json',
        'keylock_number',
        'vend_channel_error_logs_json',
        'vend_channels_json',
        'vend_channel_totals_json',
        'vend_type_id',
    ];

    // relationships
    public function category()
    {
        return $this->morphOne(Category::class, 'modelable');
    }

    public function latestVendBinding()
    {
        return $this->hasOne(VendBinding::class)->latest('begin_date');
    }

    public function vendChannels()
    {
        return $this->hasMany(VendChannel::class)->where('code', '<', 1000)->where('capacity', '>', 0)->orderBy('code');
    }

    public function outOfStockVendChannels()
    {
        return $this->vendChannels()->where('qty', '=', 0);
    }

    public function vendTemps()
    {
        return $this->hasMany(VendTemp::class)->where('type', VendTemp::TYPE_CHAMBER);
    }

    public function vendTempsEvaporator()
    {
        return $this->hasMany(VendTemp::class)->where('type', VendTemp::TYPE_EVAPORATOR);
    }

    public function vendType()
    {
        return $this->belongsTo(VendType::class);
    }

    // computed
    public function getVendChannelsTotalCapacityAttribute()
    {
        return $this->vendChannels->sum('capacity');
    }

    public function getVendChannelsTotalQtyAttribute()
    {
        return $this->vendChannels->sum('qty');
    }

    public function getVendChannelsOutOfStockAttribute()
    {
        return $this->outOfStockVendChannels->count();
    }

    public function getVendChannelsCountAttribute()
    {
        return $this->vendChannels->count();
    }

    public function getVendChannelsErrorLogsActiveAttribute()
    {
        $count = 0;

        $count = $this->vendChannels->map(function($vendChannel) {
            $vendChannel->activeErrorCount = $vendChannel->vendChannelErrorLogs->reduce(function($carry, $vendChannelErrorLog) {
                if(!$vendChannelErrorLog->is_error_cleared and $vendChannelErrorLog->vendChannelError->code != 4 and $vendChannelErrorLog->vendChannelError->code != 5 and $vendChannelErrorLog->vendChannelError->code != 7) {
                    $carry += 1;
                }
                return $carry;
            });
            return $vendChannel;
        })->sum('activeErrorCount');

        return $count;
    }

    // scopes
    public function scopeFilterIndex($query, $request)
    {
        $isOnline = $request->is_online != null ? $request->is_online : 'true';
        $countryId = $request->country_id != null ? (int)$request->country_id : 1;
        $sortKey = $request->sortKey ? $request->sortKey : 'code';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return $query->when($request->code, function($query, $search) {
            $query->where('code', 'LIKE', "%{$search}%");
        })
        ->when($request->serialNum, function($query, $search) {
            $query->where('serial_num', 'LIKE', "%{$search}%");
        })
        ->when($request->customer_code, function($query, $search) {
            $query->whereHas('latestVendBinding.customer', function($query) use ($search) {
                $query->where('code', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->customer_name, function($query, $search) {
            $query->whereHas('latestVendBinding.customer', function($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->categories, function($query, $search) {
            $query->whereHas('latestVendBinding.customer.category', function($query) use ($search) {
                $query->whereIn('id', $search);
            });
        })
        ->when($request->categoryGroups, function($query, $search) {
            $query->whereHas('latestVendBinding.customer.category.categoryGroup', function($query) use ($search) {
                $query->whereIn('id', $search);
            });
        })
        ->when($countryId, function($query, $search) {
            // dd($search);
            $query->whereHas('latestVendBinding.customer.deliveryAddress', function($query) use ($search) {
                $query->where('country_id', $search);
            });
        })
        ->when($request->tempHigherThan, function($query, $search) {
            if(is_numeric($search)) {
                $query->where('temp', '>=', $search * 10);
            }
        })
        ->when($request->vend_channel_error_id, function($query, $search) {
            if($search === 'errors_only') {
                $query->whereHas('vendChannels.vendChannelErrorLogs', function($query) {
                   $query->where('is_error_cleared', false);
                });
            }else if($search !== null) {
                $query->whereHas('vendChannels.vendChannelErrorLogs', function($query) use ($search) {
                    $query->where('vend_channel_error_id', $search)->where('is_error_cleared', false);
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
                $query->where('is_online', $search);
            }
        })
        ->when($sortKey, function($query, $search) use ($sortBy) {

            if(strpos($search, '->')) {
                $inputSearch = explode("->", $search);
                $query->orderByRaw('LENGTH(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")))'.(filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                ->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }else {
                $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }

        });
    }

    public function scopeFilterTransactionIndex($query, $request)
    {
        $sortKey = $request->sortKey ? $request->sortKey : 'transaction_datetime';
        $sortBy = $request->sortBy ? $request->sortBy : false;
        $startDate =  $request->date_from ?? Carbon::today()->startOfMonth()->toDateString();
        $endDate =  $request->date_to ?? Carbon::today()->toDateString();

        return $query->when($request->codes, function($query, $search) {
            $query->whereHas('vend', function($query) use ($search) {
                $query->whereIn('id', $search);
            });
        })
        ->when($request->errors, function($query, $search) {
            $query->whereHas('vendChannel.vendChannelErrorLogs', function($query) use ($search) {
                $query->where('vend_channel_error_id', 'IN', $search)->where('is_error_cleared', false);
            });
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
        ->when($startDate, function($query, $search) {
            $query->whereDate('transaction_datetime', '>=', $search);
        })
        ->when($endDate, function($query, $search) {
            $query->whereDate('transaction_datetime', '<=', $search);
        })
        ->when($sortKey, function($query, $search) use ($sortBy) {
            $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
        });
    }
}
