<?php

namespace App\Models;
use App\Models\Scopes\OperatorProductFilterScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    const MEASUREMENT_UNIT_L = 'L';
    const MEASUREMENT_UNIT_ML = 'ml';
    const MEASUREMENT_UNIT_G = 'g';
    const MEASUREMENT_UNIT_KG = 'kg';
    const MEASUREMENT_UNIT_PCS = 'pcs';

    const MEASUREMENT_UNIT_MAPPINGS = [
        self::MEASUREMENT_UNIT_L => 'L',
        self::MEASUREMENT_UNIT_ML => 'ml',
        self::MEASUREMENT_UNIT_G => 'g',
        self::MEASUREMENT_UNIT_KG => 'kg',
        self::MEASUREMENT_UNIT_PCS => 'pcs',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new OperatorProductFilterScope);
    }

    protected $fillable = [
        'cms_refer_id',
        'code',
        'name',
        'remarks',
        'desc',
        'is_active',
        'is_commission',
        'is_inventory',
        'is_supermarket_fee',
        'category_id',
        'category_group_id',
        'operator_id',
        'measurement_count',
        'measurement_unit',
        'measurement_value',
    ];

    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->orderBy('sequence');
    }

    // public function productImages()
    // {
    //     $this->whereHasMorph('attachments', )
    // }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function categoryGroup()
    {
        return $this->belongsTo(CategoryGroup::class);
    }

    public function latestUnitCost()
    {
        return $this->hasOne(UnitCost::class)->where('is_current', true)->orderBy('date_from', 'desc')->orderBy('created_at', 'desc');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function productUoms()
    {
        return $this->hasMany(ProductUom::class, 'product_id')->orderBy('value');
    }

    public function thumbnail()
    {
        return $this->morphOne(Attachment::class, 'modelable')->ofMany('type', 'min');
    }

    public function unitCosts()
    {
        return $this->hasMany(UnitCost::class)->orderBy('is_current', 'desc')->orderBy('date_from', 'desc')->orderBy('created_at', 'desc');
    }

    public function vendChannels()
    {
        return $this->hasMany(VendChannel::class);
    }

    public function vendTransactions()
    {
        return $this->hasMany(VendTransaction::class);
    }

    // mutators
    protected function isInventory(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: fn ($value) => $value ? true : false,
        );
    }

    protected function isCommission(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: fn ($value) => $value ? true : false,
        );
    }

    protected function isSupermarketFee(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: fn ($value) => $value ? true : false,
        );
    }

    // scopes
    public function scopeFilterIndex($query, $request)
    {
        // dd($request->all());
        $isActive = isset($request->is_active) ? $request->is_active : 1;
        $isInventory = isset($request->is_inventory) ? $request->is_inventory : 1;

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
            }else {
                $search = [$search];
            }
            $query->whereHas('vendChannels.vend', function($query) use ($search) {
                $query->whereIn('code', $search);
            });
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
        ->when($request->customer_code, function($query, $search) {
            $query->whereHas('vendChannels.vend.customer', function($query) use ($search) {
                $query->where('code', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->customer_name, function($query, $search) {
            $query->whereHas('vendChannels.vend.customer', function($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            })
            ->orWhereHas('vendChannels.vend', function($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->categories, function($query, $search) {
            $query->whereHas('vendChannels.vend.customer.category', function($query) use ($search) {
                $query->whereIn('id', $search);
            });
        })
        ->when($request->categoryGroups, function($query, $search) {
            $query->whereHas('vendChannels.vend.customer.category.categoryGroup', function($query) use ($search) {
                $query->whereIn('id', $search);
            });
        })
        ->when($request->is_binded_customer, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->has('vendChannels.vend.customer');
                }else {
                    $query->doesntHave('vendChannels.vend.customer');
                }
            }
        })
        ->when($request->location_type_id, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('vendChannels.vend.customer', function($query) use ($search) {
                    $query->where('location_type_id', $search);
                });
            }
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('operator', function($query) use ($search) {
                    $query->where('operators.id', $search);
                });
            }
        })
        ->when($request->code, function($query, $search) {
            $query->where('code', 'LIKE', "%{$search}%");
        })
        ->when($request->name, function($query, $search) {
            $query->where('name', 'LIKE', "%{$search}%");
        })
        ->when($isActive, function($query, $search) {
            $query->where('is_active', $search);
        }, function($query, $search) {
            if($search !== '') {
                $query->where('is_active', $search);
            }
        })
        ->when($isInventory, function($query, $search) {
            $query->where('is_inventory', $search);
        }, function($query, $search) {
            if($search !== '') {
                $query->where('is_inventory', $search);
            }
        })
        ->when($request->is_comm_or_sf, function($query, $search) {
            switch($search) {
                case 'comm':
                    $query->where('is_commission', 1)->where('is_supermarket_fee', 0);
                    break;
                case 'sf':
                    $query->where('is_commission', 0)->where('is_supermarket_fee', 1);
                    break;
                case 'both':
                    $query->where(function($query)  {
                        $query->where('is_commission', 1)->orWhere('is_supermarket_fee', 1);
                    });
                    break;
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
    }
}
