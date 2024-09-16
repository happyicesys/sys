<?php

namespace App\Models;

use App\Models\Scopes\OperatorCustomerFilterScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    const DAYS_MAPPING = [
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
        7 => 'Sun',
        8 => 'Holiday Eve',
    ];

    const FREQUENCY_PER_WEEK_STATUSES_MAPPING = [
        1 => 'Less Than 1 Time',
        2 => '1 Time',
        3 => 'More Than 1 Time',
    ];

    const RUNNING_NUMBER_INIT = 20000;

    const STATUS_NEW = 4;
    const STATUS_PENDING = 3;
    const STATUS_ACTIVE = 2;
    const STATUS_INACTIVE = 1;

    const ADDRESS_TYPE_BILLING = 1;
    const ADDRESS_TYPE_DELIVERY = 2;

    const STATUSES_MAPPING = [
        self::STATUS_NEW => 'New',
        self::STATUS_PENDING => 'Pending',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_INACTIVE => 'Not Active',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new OperatorCustomerFilterScope);
    }

    protected $casts = [
        'account_manager_json' => 'json',
        'begin_date' => 'datetime',
        'cms_invoice_history' => 'json',
        'person_json' => 'json',
        'last_invoice_date' => 'datetime',
        'next_invoice_date' => 'datetime',
        'snap_parameter_json' => 'json',
        'snap_vend_channels_json' => 'json',
        'snap_vend_channel_error_logs_json' => 'json',
        'snap_vend_status_json' => 'json',
        'termination_date' => 'datetime',
        'totals_json' => 'json',
        // json declaration for vend
        'acb_vmc_pa_json' => 'json',
        'acb_status_json' => 'json',
        'apk_ver_json' => 'json',
        'begin_date' => 'datetime',
        'last_updated_at' => 'datetime',
        'mqtt_last_updated_at' => 'datetime',
        'parameter_json' => 'json',
        'preferred_visit_days_json' => 'json',
        'statistics1_json' => 'json',
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
        'account_manager_json',
        'begin_date',
        'category_id',
        'cms_invoice_history',
        'code',
        'created_at',
        'person_json',
        'first_transaction_id',
        'frequency_per_week_status',
        'name',
        'is_active',
        'last_invoice_date',
        'location_type_id',
        'next_invoice_date',
        'next_invoice_driver_id',
        'operator_id',
        'ops_note',
        // for cms person id
        'person_id',
        'power_socket_key_number',
        'preferred_visit_days_json',
        'profile_id',
        'selling_price_type',
        'snap_parameter_json',
        'snap_vend_channels_json',
        'snap_vend_channel_error_logs_json',
        'snap_vend_status_json',
        'status_id',
        'termination_date',
        'totals_json',
        'virtual_customer_code',
        'virtual_customer_prefix',
        'zone_id',
    ];

    // mutator
    public function getRefIDAttribute()
    {
        return $this->id + self::RUNNING_NUMBER_INIT;
    }

    // relationships
    public function addresses()
    {
        return $this->morphMany(Address::class, 'modelable');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->orderBy('sequence');
    }

    public function billingAddress()
    {
        return $this->morphOne(Address::class, 'modelable')->ofMany('type', 'min');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function contact()
    {
        return $this->morphOne(Contact::class, 'modelable');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveryAddress()
    {
        return $this->morphOne(Address::class, 'modelable')->ofMany('type', 'max');
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function firstTransaction()
    {
        return $this->belongsTo(Transaction::class, 'first_transaction_id');
    }

    public function locationType()
    {
        return $this->belongsTo(LocationType::class);
    }

    public function nextInvoiceDriver()
    {
        return $this->belongsTo(User::class, 'next_invoice_driver_id');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function tagBindings()
    {
        return $this->hasMany(TagBinding::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function vend()
    {
        return $this->hasOne(Vend::class)->latest('begin_date')->latest('created_at');
    }

    public function vends()
    {
        return $this->hasMany(Vend::class);
    }

    public function vendRecords()
    {
        return $this->hasMany(VendRecord::class);
    }

    public function vendTransactions()
    {
        return $this->hasMany(VendTransaction::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    // for the use of cleanCustomerSeeder before deprecate
    public function vendBindings()
    {
        return $this->hasMany(VendBinding::class);
    }

    public function latestVendBinding()
    {
        return $this->hasOne(VendBinding::class)->where('is_active', true)->latest('begin_date');
    }

    // vend_transactions with date range
    public function daysVendTransactions($from = 0, $to = 0)
    {
        return $this->vendTransactions()
                    // ->isSuccessful()
                    ->where('transaction_datetime', '>=', Carbon::today()->subDays($from)->startOfDay())
                    ->where('transaction_datetime', '<=', Carbon::today()->subDays($to)->endOfDay());
    }

    // vend_records with customer begin date and termination date
    public function lifetimeVendRecords()
    {
        return $this->vendRecords()
                    ->where('date', '>=', Carbon::parse($this->begin_date)->startOfDay())
                    ->where('date', '<=', ($this->termination_date ? Carbon::parse($this->termination_date)->endOfDay() : Carbon::today()->endOfDay()));
    }

    // vend_records with date range
    public function daysVendRecords($from = 0, $to = 0)
    {
        return $this->vendRecords()
                    ->where('date', '>=', Carbon::today()->subDays($from)->startOfDay())
                    ->where('date', '<=', Carbon::today()->subDays($to)->endOfDay());
    }

    // scopes
    public function scopeFilterIndex($query, $request)
    {
        return $query->when($request->categories, function($query, $search) {
            $query->whereHas('categories', function($query) use ($search) {
                $query->whereIn('id', $search);
            });
        })
        ->when($request->categoryGroups, fn($query, $input) => $query->whereHas('category.categoryGroup', function($query) use ($input) {
            $query->whereIn('id', $input);
        }))
        ->when($request->code, fn($query, $input) => $query->where('code', 'LIKE', '%'.$input.'%'))
        ->when($request->created_in, fn($query, $input) => $query->whereDate('created_at', '>=', Carbon::createFromFormat('m-Y', $input)->startOfMonth())->whereDate('created_at', '<=', Carbon::createFromFormat('m-Y', $input)->endOfMonth()))
        ->when($request->customer, function($query, $search) {
            $query->where(function($query) use ($search) {
                $query->where('customers.virtual_customer_prefix', 'LIKE', "{$search}%")
                        ->orWhere('customers.virtual_customer_code', 'LIKE', "{$search}%")
                        ->orWhere('customers.name', 'LIKE', "%{$search}%");
                });
        })
        ->when($request->frequency_per_week_status, function($query, $search) {
            if($search != 'all') {
                $query->where('frequency_per_week_status', $search);
            }
        })
        ->when($request->is_active, function($query, $search) use ($request) {
            if($search != 'all') {
                $query->where('customers.is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
            }
        })
        ->when($request->is_binded_vend, function($query, $search) {
            if($search != 'all') {
                $searchBoolean = filter_var($search, FILTER_VALIDATE_BOOLEAN);
                if($searchBoolean)
                    $query->whereHas('vend');
                else {
                    $query->doesntHave('vend');
                }
            }
        })
        ->when($request->is_cms, function($query, $search) {
            if($search != 'all') {
                $searchBoolean = filter_var($search, FILTER_VALIDATE_BOOLEAN);
                if($searchBoolean)
                    $query->whereNotNull('person_id');
                else {
                    $query->whereNull('person_id');
                }
            }
        })
        ->when($request->handled_by, fn($query, $input) => $query->where('handled_by', $input))
        ->when($request->location_types, function($query, $search) {
            if(!in_array('all', $search)){
                $query->whereIn('location_type_id', $search);
            }
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
                $query->where('customers.operator_id', $search);
            }
        })
        ->when($request->operators, function($query, $search) {
            if(!in_array('all', $search)){
                $query->whereIn('customers.operator_id', $search);
            }
        })
        ->when($request->preferredDays, function($query, $search) {
            $query->where(function($subQuery) use ($search) {
                foreach ($search as $day) {
                    $subQuery->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(customers.preferred_visit_days_json, '$.\"$day\"')) = 'true'");
                }
            });
        })
        ->when($request->price_template_id, fn($query, $input) => $query->where('price_template_id', $input))
        ->when($request->profile_id, fn($query, $input) => $query->where('profile_id', $input))
        ->when($request->ref_id, function($query, $search) {
            $query->where('customers.id', '=', ($search - 20000));
        })
        ->when($request->selling_price_type, fn($query, $input) => $query->where('selling_price_type', $input))
        ->when($request->status, fn($query, $input) => $query->where('status_id', $input))
        ->when($request->vend_code, function($query, $search) {
            $query->whereIn('customers.id',
                Vend::where('code', 'LIKE', '%'.$search.'%')
                ->pluck('customer_id')
            );
        })
        ->when($request->vend_model_id, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('vend', function($query) use ($search) {
                    $query->where('vend_model_id', $search);
                });
            }
        })
        ->when($request->vendPrefixes, function($query, $search) {
            $query->whereHas('vend', function($query) use ($search) {
                $query->whereIn('vend_prefix_id', $search);
            });
        })
        ->when($request->zones, function($query, $search) {
            if(!in_array('all', $search)){
                $query->whereIn('zone_id', $search);
            }
        })
        ->when($request->sortKey, function($query, $search) use ($request) {
            // Check if the sortKey involves a JSON field
            if(strpos($search, '->')) {
                $inputSearch = explode("->", $search);
                if(
                    $search === 'vend_transaction_totals_json->vend_records_amount_latest' or
                    $search === 'vend_transaction_totals_json->vend_records_amount_average_day' or
                    $search === 'vend_transaction_totals_json->vend_records_thirty_days_amount_average' or
                    $search === 'vend_transaction_totals_json->vend_records_thirty_days_amount'
                ) {
                    $query->orderByRaw('(CAST(json_unquote(json_extract(`'. 'totals_json' .'`, "$.'.$inputSearch[1].'")) AS DECIMAL(10,2))) ' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'));
                }else {
                    $query->orderByRaw('LENGTH(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")))'.(filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'));
                }

                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            } else {
                // Handle sorting for non-JSON fields
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
            }
        });

    }
}
