<?php

namespace App\Models;

use App\Models\VendTemp;
use App\Models\Scopes\OperatorVendFilterScope;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vend extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::addGlobalScope(new OperatorVendFilterScope);
    }

    protected $casts = [
        'apk_ver_json' => 'json',
        'begin_date' => 'datetime',
        'last_updated_at' => 'datetime',
        'parameter_json' => 'json',
        'temp_updated_at' => 'datetime',
        'termination_date' => 'datetime',
        'vend_channel_error_logs_json' => 'json',
        'vend_channels_json' => 'json',
        'vend_channel_totals_json' => 'json',
        'vend_criteria_score_json' => 'json',
        'vend_criteria_weightage_json' => 'json',
        'vend_transaction_totals_json' => 'json',

    ];

    protected $fillable = [
        'amount_average_day',
        'apk_ver_json',
        'begin_date',
        'code',
        'serial_num',
        'name',
        'temp',
        'temp_updated_at',
        'coin_amount',
        'firmware_ver',
        'is_active',
        'is_customer',
        'is_door_open',
        'is_mqtt',
        'is_offline_notification_sent',
        'is_online',
        'is_sensor_normal',
        'is_temp_error',
        'last_updated_at',
        'mqtt_updated_at',
        'parameter_json',
        'private_key',
        'product_mapping_id',
        'termination_date',
        'keylock_number',
        'vend_channel_error_logs_json',
        'vend_channels_json',
        'vend_channel_totals_json',
        'vend_criteria_score_json',
        'vend_criteria_weightage_json',
        'vend_transaction_totals_json',
        'vend_type_id',
    ];

    // relationships
    public function category()
    {
        return $this->morphOne(Category::class, 'modelable');
    }

    public function currentOperator()
    {
        return $this->operators()->latest('operator_vend.created_at')->limit(1);
    }

    public function latestOperator()
    {
        return $this->operators()->latest('operator_vend.created_at')->limit(1);
    }

    public function latestVendBinding()
    {
        return $this->hasOne(VendBinding::class)->where('is_active', true)->latest('begin_date');
    }

    public function latestVendBindingAll()
    {
        return $this->hasOne(VendBinding::class)->latest('begin_date');
    }

    public function vendBindings()
    {
        return $this->hasMany(VendBinding::class);
    }

    public function vendChannels()
    {
        return $this->hasMany(VendChannel::class)->where('is_active', true)->where('capacity', '>', 0)->orderBy('code');
    }

    public function vendChannelsWithoutClaw()
    {
        return $this->hasMany(VendChannel::class)->where('is_active', true)->where('capacity', '>', 0)->where(function($query) {
            $query->where('code', '<', 50)->orWhere('code', '>', 59);
        })->orderBy('code');
    }

    // public function vendCriterias()
    // {
    //     return $this->belongsToMany(VendCriteria::class)->using(VendCriteriaBinding::class);
    // }

    public function vendSnapshots()
    {
        return $this->hasMany(VendSnapshot::class)->latest();
    }

    public function operators()
    {
        return $this->belongsToMany(Operator::class);
    }

    public function primaryOperator()
    {
        return $this->belongsToMany(Operator::class)->withPivot('is_primary', true);
    }

    public function productMapping()
    {
        return $this->belongsTo(ProductMapping::class);
    }

    public function outOfStockVendChannels()
    {
        return $this->vendChannels()->where('qty', '=', 0);
    }

    public function vendCriterias()
    {
        return $this->belongsToMany(VendCriteria::class)->using(VendCriteriaBinding::class);
    }

    public function vendFans()
    {
        return $this->hasMany(VendFan::class);
    }

    public function vendTemps()
    {
        return $this->hasMany(VendTemp::class);
    }

    public function vendThisMonthTransactions()
    {
        return $this->hasMany(VendTransaction::class)
                    ->isSuccessful()
                    ->where('transaction_datetime', '>=', Carbon::today()->startOfMonth()->startOfDay())
                    ->where('transaction_datetime', '<=', Carbon::today()->endOfMonth()->endOfDay());
    }

    public function vendLastMonthTransactions()
    {
        return $this->hasMany(VendTransaction::class)
                    ->isSuccessful()
                    ->where('transaction_datetime', '>=', Carbon::today()->subMonth()->startOfMonth()->startOfDay())
                    ->where('transaction_datetime', '<=', Carbon::today()->subMonth()->endOfMonth()->endOfDay());
    }

    public function vendLast2MonthTransactions()
    {
        return $this->hasMany(VendTransaction::class)
                    ->isSuccessful()
                    ->where('transaction_datetime', '>=', Carbon::today()->subMonths(2)->startOfMonth()->startOfDay())
                    ->where('transaction_datetime', '<=', Carbon::today()->subMonths(2)->endOfMonth()->endOfDay());
    }

    public function vendThirtyDaysTransactions()
    {
        return $this->hasMany(VendTransaction::class)
                    ->isSuccessful()
                    ->where('transaction_datetime', '>=', Carbon::today()->subDays(29)->startOfDay())
                    ->where('transaction_datetime', '<=', Carbon::today()->endOfDay());
    }

    public function vendSevenDaysTransactions()
    {
        return $this->hasMany(VendTransaction::class)
                    ->isSuccessful()
                    ->where('transaction_datetime', '>=', Carbon::today()->subDays(6)->startOfDay())
                    ->where('transaction_datetime', '<=', Carbon::today()->endOfDay());
    }

    public function vendYesterdayTransactions()
    {
        return $this->hasMany(VendTransaction::class)
                    ->isSuccessful()
                    ->where('transaction_datetime', '>=', Carbon::yesterday()->startOfDay())
                    ->where('transaction_datetime', '<=', Carbon::yesterday()->endOfDay());
    }

    public function vendTodayTransactions()
    {
        return $this->hasMany(VendTransaction::class)
                    ->isSuccessful()
                    ->where('transaction_datetime', '>=', Carbon::today()->startOfDay())
                    ->where('transaction_datetime', '<=', Carbon::today()->endOfDay());
    }

    public function vendRecordsLatest()
    {
        return $this->hasMany(VendRecord::class)
                    ->where('date', '>=', Carbon::parse($this->begin_date)->startOfDay())
                    ->where('date', '<=', $this->termination_date ? Carbon::parse($this->termination_date)->endOfDay() : Carbon::today()->endOfDay());
    }

    public function vendRecordsThirtyDays()
    {
        return $this->hasMany(VendRecord::class)
                    ->where('date', '>=', Carbon::today()->subDays(29)->startOfDay())
                    ->where('date', '<=', Carbon::today()->endOfDay());
    }

    public function vendTransactions()
    {
        return $this->hasMany(VendTransaction::class);
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

    public function getVendChannelsTotalCapacityWithoutClawAttribute()
    {
        return $this->vendChannelsWithoutClaw->sum('capacity');
    }

    public function getVendChannelsTotalQtyWithoutClawAttribute()
    {
        return $this->vendChannelsWithoutClaw->sum('qty');
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
        $isDoorOpen = $request->is_door_open != null ? $request->is_door_open : 'all';
        $isOnline = $request->is_online != null ? $request->is_online : 'all';
        $isSensor = $request->is_sensor != null ? $request->is_sensor : 'all';

        return $query->when($request->has('visited'), function($query, $search) use ($request) {
            if($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            }else {
                $query->whereRaw('1 = 0');
            }
        })
        ->when($request->codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
                $query->whereIn('vends.code', $search);
            }else {
                $query->where('vends.code', 'LIKE', "%{$search}%");
            }
        })
        ->when($request->channel_codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
            }else {
                $search = [$search];
            }
            $query->whereHas('vendChannels', function($query) use ($search) {
                $query->whereIn('code', $search);
            });
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
            $query->where(function($query) use ($search) {
                $query
                    ->whereHas('latestVendBinding.customer', function($query) use ($search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhere('vends.name', 'LIKE', "%{$search}%");
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
        ->when($request->fanSpeedLowerThan, function($query, $search) {
            if(is_numeric($search)) {
                $query->where('parameter_json->fan', '<=', $search)->where('parameter_json->fan', '>', 0);
            }
        })
        ->when($isDoorOpen, function($query, $search) {
            if($search != 'all') {
                $query->where('parameter_json->door', '=', $search);
            }
        })
        ->when($request->is_binded_customer, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->has('latestVendBinding');
                }else {
                    $query->doesntHave('latestVendBinding');
                }
            }
        })
        ->when($request->tempHigherThan, function($query, $search) {
            if(is_numeric($search)) {
                $query->where('temp', '>=', $search * 10);
            }
        })
        ->when($request->tempDeltaHigherThan, function($query, $search) {
            if(is_numeric($search)) {
                $query
                    ->whereNotNull('parameter_json->t2')
                    ->where('parameter_json->t2', '!=', VendTemp::TEMPERATURE_ERROR)
                    ->whereRaw('temp - json_extract(parameter_json, "$.t2") > ?', [$search * 10]);
            }
        })
        ->when($request->errors, function($query, $search) {
            if(in_array('errors_only', $search)) {
                $query->whereHas('vendChannels.vendChannelErrorLogs', function($query) {
                   $query->where('is_error_cleared', false);
                });
            }else {
                $query->whereHas('vendChannels.vendChannelErrorLogs', function($query) use ($search) {
                    $query->whereIn('vend_channel_error_id', $search)->where('is_error_cleared', false);
                });
            }

        })
        ->when($request->location_type_id, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('latestVendBinding.customer', function($query) use ($search) {
                    $query->where('location_type_id', $search);
                });
            }
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('operators', function($query) use ($search) {
                    $query->where('operators.id', $search);
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
        ->when($isSensor, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->whereIn('parameter_json->Sensor', ['1', '3', '5', '7', '9']);
                }else {
                    $query->whereIn('parameter_json->Sensor', ['0', '2', '4', '6', '8', '10']);
                }
            }
        })
        ->when($request->lastVisitedGreaterThan, function($query, $search) {
                // dd('here');
                $query->whereHas('latestVendBinding.customer', function($query) use ($search) {
                    $query->whereDate('last_invoice_date', '<=', Carbon::now()->subDays($search)->toDateString());
                });
        })
        ->when($request->balanceStockLessThan, function($query, $search) {
            $query->where('balance_percent', '<=', $search);
        })
        ->when($request->remainingSkuLessThan, function($query, $search) {
            $query->where('out_of_stock_sku_percent', '>=', (100 - $search));
        })
        ->when($request->sortKey, function($query, $search) use ($request) {
            if(strpos($search, '->')) {
                $inputSearch = explode("->", $search);
                $query->orderByRaw('LENGTH(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")))'.(filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                ->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }else {
                if($search == 'balance_percent' or $search == 'out_of_stock_sku_percent') {
                    $query->orderByRaw('ISNULL('.$search.'), '.$search.' '.(filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'));
                }else {
                    $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                }
            }


            if($search === 'vends.is_online') {
                $query->orderBy('vends.code', 'asc');
            }

        });
    }
}
