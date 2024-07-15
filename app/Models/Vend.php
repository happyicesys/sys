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

    const ATTACHMENT_TYPE_LOG = 1;
    const ATTACHMENT_TYPE_MEDIA_CONTENT = 2;

    const DEVICE_TYPE_ANDROID = 'ANDROID';
    const DEVICE_TYPE_ZC83A = 'ZC-83A';
    const DEVICE_TYPE_ZC328 = 'ZC-328';
    const DEVICE_TYPE_INPAD3101 = 'INPAD3101';

    const DEVICE_TYPE_MAPPINGS = [
        self::DEVICE_TYPE_ANDROID => 'Android',
        self::DEVICE_TYPE_ZC83A => 'ZC-83A',
        self::DEVICE_TYPE_ZC328 => 'ZC-328',
        self::DEVICE_TYPE_INPAD3101 => 'INPAD3101',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new OperatorVendFilterScope);
    }

    protected $casts = [
        'acb_vmc_pa_json' => 'json',
        'acb_status_json' => 'json',
        'apk_ver_json' => 'json',
        'begin_date' => 'datetime',
        'customer_movement_history_json' => 'json',
        'last_updated_at' => 'datetime',
        'mqtt_last_updated_at' => 'datetime',
        'original_vend_channels_json' => 'json',
        'parameter_json' => 'json',
        'statistics1_json' => 'json',
        'temp_updated_at' => 'datetime',
        'termination_date' => 'datetime',
        'vend_channel_error_logs_json' => 'json',
        'vend_channels_json' => 'json',
        'vend_channel_totals_json' => 'json',
        'vend_criteria_score_json' => 'json',
        'vend_criteria_weightage_json' => 'json',
        'vend_temp_alert_json' => 'json',
        'vend_transaction_totals_json' => 'json',

    ];

    protected $fillable = [
        'acb_vmc_pa_json',
        'acb_status_json',
        'amount_average_day',
        'apk_ver_json',
        'begin_date',
        'cashless_terminal_id',
        'code',
        'customer_id',
        'customer_movement_history_json',
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
        'is_mqtt_active',
        'is_mqtt_offline_notified',
        'is_offline_notification_sent',
        'is_online',
        'is_sensor_normal',
        'is_temp_error',
        'is_testing',
        'key_id',
        'last_ip_address',
        'last_updated_at',
        'mqtt_last_updated_at',
        'mqtt_updated_at',
        'operator_id',
        'original_vend_channels_json',
        'parameter_json',
        'private_key',
        'product_mapping_id',
        'simcard_id',
        'statistics1_json',
        'termination_date',
        'keylock_number',
        'upcoming_product_mapping_id',
        'vend_channel_error_logs_json',
        'vend_channels_json',
        'vend_channel_totals_json',
        'vend_config_id',
        'vend_criteria_score_json',
        'vend_criteria_weightage_json',
        'vend_model_id',
        'vend_prefix_id',
        'vend_serial_number_id',
        'vend_temp_alert_json',
        'vend_transaction_totals_json',
        'vend_type_id',
        'vend_vend_config_version',
        'virtual_firmware_ver',
    ];

    // relationships
    public function category()
    {
        return $this->morphOne(Category::class, 'modelable');
    }

    public function cashlessTerminal()
    {
        return $this->belongsTo(CashlessTerminal::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function deliveryProductMappingVends()
    {
        return $this->hasMany(DeliveryProductMappingVend::class);
    }

    // for the use of cleanCustomerSeeder before deprecate
    // public function vendBindings()
    // {
    //     return $this->hasMany(VendBinding::class);
    // }

    public function key()
    {
        return $this->belongsTo(Key::class);
    }

    public function latestVendBinding()
    {
        return $this->hasOne(VendBinding::class)->where('is_active', true)->latest('begin_date');
    }

    public function simcard()
    {
        return $this->belongsTo(Simcard::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
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

    public function vendConfig()
    {
        return $this->belongsTo(VendConfig::class);
    }

    public function vendModel()
    {
        return $this->belongsTo(VendModel::class);
    }

    public function vendPrefix()
    {
        return $this->belongsTo(VendPrefix::class);
    }

    public function vendSerialNumber()
    {
        return $this->belongsTo(VendSerialNumber::class);
    }

    public function vendSnapshots()
    {
        return $this->hasMany(VendSnapshot::class)->latest();
    }

    public function logs()
    {
        return $this->morphMany(Attachment::class, 'modelable')->where('type', Vend::ATTACHMENT_TYPE_LOG)->latest()->take(10);
    }

    public function mediaContents()
    {
        return $this->morphMany(Attachment::class, 'modelable')->where('type', Vend::ATTACHMENT_TYPE_MEDIA_CONTENT)->oldest();
    }

    // deprecated, will use customer operator_id instead (keep now for cleanCustomerSeeder)
    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function productMapping()
    {
        return $this->belongsTo(ProductMapping::class);
    }

    public function outOfStockVendChannels()
    {
        return $this->vendChannels()->where('qty', '=', 0);
    }

    public function upcomingProductMapping()
    {
        return $this->belongsTo(ProductMapping::class, 'upcoming_product_mapping_id');
    }

    public function vendCriterias()
    {
        return $this->belongsToMany(VendCriteria::class)->using(VendCriteriaBinding::class);
    }

    public function vendFans()
    {
        return $this->hasMany(VendFan::class);
    }

    public function vendRecords()
    {
        return $this->hasMany(VendRecord::class);
    }

    public function vendTemps()
    {
        return $this->hasMany(VendTemp::class);
    }

    public function vendTransactions()
    {
        return $this->hasMany(VendTransaction::class);
    }

    public function vendThreeDaysErrorTransactions()
    {
        return $this->daysVendTransactions(2,0)->isError();
    }

    public function vendSevenDaysErrorTransactions()
    {
        return $this->daysVendTransactions(6,0)->isError();
    }

    public function vendType()
    {
        return $this->belongsTo(VendType::class);
    }

    public function daysVendTransactions($from = 0, $to = 0)
    {
        return $this->vendTransactions()
                    // ->isSuccessful()
                    ->where('transaction_datetime', '>=', Carbon::today()->subDays($from)->startOfDay())
                    ->where('transaction_datetime', '<=', Carbon::today()->subDays($to)->endOfDay());
    }

    public function lifetimeVendRecords()
    {
        return $this->vendRecords()
                    ->where('date', '>=', Carbon::parse($this->begin_date)->startOfDay())
                    ->where('date', '<=', ($this->termination_date ? Carbon::parse($this->termination_date)->endOfDay() : Carbon::today()->endOfDay()));
    }

    public function daysVendRecords($from = 0, $to = 0)
    {
        return $this->vendRecords()
                    ->where('date', '>=', Carbon::today()->subDays($from)->startOfDay())
                    ->where('date', '<=', Carbon::today()->subDays($to)->endOfDay());
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
        $request->merge([
            'is_door_open' => $request->is_door_open != null ? $request->is_door_open : 'all',
            'is_online' => $request->is_online != null ? $request->is_online : 'all',
            'is_sensor' => $request->is_sensor != null ? $request->is_sensor : 'all',
            'is_testing' => $request->is_testing != null ? $request->is_testing : 'all',
        ]);

        return $query->when($request->has('visited'), function($query, $search) use ($request) {
            if($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            }else {
                $query->whereRaw('1 = 0');
            }
        })
        ->when($request->cashless_terminal_id, function($query, $search) {
            if($search != 'all') {
                $query->where('vends.cashless_terminal_id', $search);
            }
        })
        ->when($request->account_manager_name, function($query, $search) {
            $query->where('customers.account_manager_json->name', 'LIKE', "{$search}%");
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

            $query->whereIn('vends.id', DB::table('vend_channels')->select('vend_id')->whereIn('code', $search)->where('vend_channels.is_active', true)->pluck('vend_id'));
        })
        ->when($request->serialNum, function($query, $search) {
            $query->where('serial_num', 'LIKE', "%{$search}%");
        })
        ->when($request->customer, function($query, $search) {
            if(strpos($search, "-")) {
                $searchArray = explode("-", $search);
                $query->whereHas('customer', function($query) use ($searchArray) {
                    $query->where('virtual_customer_prefix', $searchArray[0])
                        ->where('virtual_customer_code', 'LIKE', "{$searchArray[1]}%");
                });
            }else {
                $query->whereHas('customer', function($query) use ($search) {
                    $query->where('virtual_customer_prefix', 'LIKE', "{$search}%")
                        ->orWhere('virtual_customer_code', 'LIKE', "{$search}%")
                        ->orWhere('name', 'LIKE', "%{$search}%");
                });
            }
        })
        ->when($request->product_code, function($query, $search) {
            $query->where('products.code', 'LIKE', "%{$search}%");
        })
        ->when($request->product_name, function($query, $search) {
            $query->where('products.name', 'LIKE', "%{$search}%");
        })
        ->when($request->categories, function($query, $search) {
            $query->whereIn('categories.id', $search);
        })
        ->when($request->categoryGroups, function($query, $search) {
            $query->whereIn('category_groups.id', $search);
        })
        ->when($request->fanSpeedLowerThan, function($query, $search) {
            if(is_numeric($search)) {
                $query->where('parameter_json->fan', '<=', $search)->where('parameter_json->fan', '>', 0);
            }
        })
        ->when($request->is_active, function($query, $search) use ($request) {
        $columnName =  $request->indexType ? $request->indexType . '.is_active' : 'vends.is_active';
            if($search != 'all') {
                $query->where($columnName, filter_var($search, FILTER_VALIDATE_BOOLEAN));
            }
        })
        ->when($request->is_testing, function($query, $search) {
            if($search != 'all') {
                $query->where('vends.is_testing', filter_var($search, FILTER_VALIDATE_BOOLEAN));
            }
        })
        ->when($request->status, function($query, $search) use ($request) {
            // dd('here', $search, $request->all(), $query->toSql());
            if($search != 'all') {
                switch($search) {
                    case 'factory':
                        $query->where('vends.is_testing', true)->where('vends.is_active', false);
                        break;
                    case 'active':
                        $query->where('vends.is_active', true)->where('vends.is_testing', false);
                        break;
                    case 'inactive':
                        $query->where('vends.is_active', false)->where('vends.is_testing', false);
                        break;
                }
            }

            // dd($query->toSql());
        })
        ->when($request->is_mqtt, function($query, $search) {
            if($search != 'all') {
                $query->where('vends.is_mqtt', filter_var($search, FILTER_VALIDATE_BOOLEAN));
            }
        })
        ->when($request->is_mqtt_active, function($query, $search) {
            if($search != 'all') {
                $query->where('vends.is_mqtt', true)->where('vends.is_mqtt_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
            }
        })
        ->when($request->is_door_open, function($query, $search) {
            if($search != 'all') {
                $query->where('parameter_json->door', '=', $search);
            }
        })
        ->when($request->simcard_id, function($query, $search) {
            if($search != 'all') {
                $query->where('vends.simcard_id', $search);
            }
        })
        ->when($request->tempHigherThan, function($query, $search) {
            if(is_numeric($search)) {
                $query->where('temp', '>=', $search * 10);
            }
        })
        ->when($request->t2HigherThan, function($query, $search) {
            if(is_numeric($search)) {
                $query->where('parameter_json->t2', '>=', $search * 10);
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
            $query->whereIn('vends.id',
                DB::table('vend_channels')
                ->select('vend_id')
                ->where('vend_channels.is_active', true)
                ->whereIn('vend_channels.id', DB::table('vend_channel_error_logs')
                    ->select('vend_channel_id')
                    ->where('is_error_cleared', false)
                    ->pluck('vend_channel_id'))
                ->pluck('vend_id')
            );
            }else {
            $query->whereIn('vends.id',
                DB::table('vend_channels')
                ->select('vend_id')
                ->where('vend_channels.is_active', true)
                ->whereIn('vend_channels.id', DB::table('vend_channel_error_logs')
                    ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_channel_error_logs.vend_channel_error_id')
                    ->select('vend_channel_id')
                    ->where('is_error_cleared', false)
                    ->whereIn('vend_channel_errors.id', $search)
                    ->pluck('vend_channel_id'))
                ->pluck('vend_id')
            );
            }
        })
        ->when($request->key_id, function($query, $search) {
            if($search != 'all') {
                $query->where('key_id', $search);
            }
        })
        ->when($request->location_type_id, function($query, $search) {
            if($search != 'all') {
                $query->where('location_type_id', $search);
            }
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
                $query->where('vends.operator_id', $search);
            }
        })
        ->when($request->operators, function($query, $search) {
            if(!in_array('all', $search)){
                $query->whereIn('vends.operator_id', $search);
            }
        })
        ->when($request->is_online, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $search = true;
                }else {
                    $search = false;
                }
                $query->where('is_online', $search);
            }
        })
        ->when($request->is_sensor, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->whereIn('parameter_json->Sensor', ['1', '3', '5', '7', '9']);
                }else {
                    $query->whereIn('parameter_json->Sensor', ['0', '2', '4', '6', '8', '10']);
                }
            }
        })
        ->when($request->lastVisitedGreaterThan, function($query, $search) {
            $query->whereDate('customers.last_invoice_date', '<=', Carbon::now()->subDays($search)->toDateString());
        })
        ->when($request->balanceStockLessThan, function($query, $search) {
            $query->where('balance_percent', '<=', $search);
        })
        ->when($request->remainingSkuLessThan, function($query, $search) {
            $query->where('out_of_stock_sku_percent', '>=', (100 - $search));
        })
        ->when($request->selling_price_type, function($query, $search) {
            $query->whereHas('customer', function($query) use ($search) {
                $query->where('selling_price_type', $search);
            });
        })
        ->when($request->apk_ver, function($query, $search) {
            $query->where('apk_ver_json->apkver', 'LIKE', "{$search}%");
        })
        ->when($request->firmware_ver, function($query, $search) {
            $search = hexdec($search);
            $query->where('parameter_json->Ver', 'LIKE', "{$search}%");
        })
        ->when($request->vend_config_id, function($query, $search) {
            if($search != 'all') {
                $query->where('vends.vend_config_id', $search);
            }
        })
        ->when($request->vend_model_id, function($query, $search) {
            if($search != 'all') {
                $query->where('vends.vend_model_id', $search);
            }
        })
        ->when($request->vend_prefix_id, function($query, $search) {
            if($search != 'all') {
                $query->where('vends.vend_prefix_id', $search);
            }
        })
        ->when($request->vendPrefixes, function($query, $search) {
            $query->whereIn('vend_prefix_id', $search);
            // $query->whereHas('vendPrefix', function($query) use ($search) {
            //     $query->whereIn('id', $search);
            // });
        })
        ->when($request->vendRecordsThirtyDaysAmountAverageLessThan, function($query, $search) {
            $query->where('virtual_vend_records_thirty_days_amount_average', '<=', $search*100);
        })
        ->when($request->sortKey, function($query, $search) use ($request) {
            if(strpos($search, '->')) {
                $inputSearch = explode("->", $search);
                if($search === 'totals_json->three_days_error_rate' or $search === 'totals_json->seven_days_error_rate') {
                $query->orderByRaw('(CAST(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")) AS DECIMAL(10,2))) ' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'));
                }else {
                $query->orderByRaw('LENGTH(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")))'.(filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'));
                }

                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
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
