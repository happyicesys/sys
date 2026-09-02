<?php

namespace App\Models;

use App\Models\Scopes\OperatorProductFilterScope;
use App\Models\Scopes\ProductAccessProductScope;
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
        static::addGlobalScope(new ProductAccessProductScope);

        // Blind SKU: stamp WHEN a product became (or stopped being) a housing, so
        // ops jobs created before the flip keep treating it as a normal product.
        static::saving(function (Product $product) {
            if ($product->isDirty('is_parent_sku')) {
                if ($product->is_parent_sku) {
                    if (empty($product->is_parent_sku_since)) {
                        $product->is_parent_sku_since = now();
                    }
                } else {
                    $product->is_parent_sku_since = null;
                }
            }
        });
    }

    protected $fillable = [
        'avg_seven_days_count',
        'category_id',
        'category_group_id',
        'cms_refer_id',
        'code',
        'warehouse_qty_source',
        'desc',
        'is_active',
        'is_available',
        'is_available_updated_at',
        'is_available_updated_by',
        'is_commission',
        'is_healthier_choice',
        'is_halal',
        'is_inventory',
        'is_parent_sku',
        'is_parent_sku_since',
        'is_supermarket_fee',
        'max_ops_job_pick_limit_json',
        'measurement_count',
        'measurement_unit',
        'measurement_value',
        'name',
        'nutri_grade',
        'operator_id',
        'product_sub_category_id',
        'remarks',
        'remarks_updated_at',
        'remarks_updated_by',
        'translated_names_json',
    ];

    protected $casts = [
        'max_ops_job_pick_limit_json' => 'json',
        'translated_names_json' => 'json',
        'is_available_updated_at' => 'datetime',
        'remarks_updated_at' => 'datetime',
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'is_commission' => 'boolean',
        'is_healthier_choice' => 'boolean',
        'is_halal' => 'boolean',
        'is_inventory' => 'boolean',
        'is_parent_sku' => 'boolean',
        'is_parent_sku_since' => 'datetime',
        'is_supermarket_fee' => 'boolean',
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
        return $this->belongsTo(Category::class)->where('classname', 'App\Models\Product');
    }

    public function categoryGroup()
    {
        return $this->belongsTo(CategoryGroup::class)->where('classname', 'App\Models\Product');
    }

    public function isAvailableUpdatedBy()
    {
        return $this->belongsTo(User::class, 'is_available_updated_by');
    }

    public function remarksUpdatedBy()
    {
        return $this->belongsTo(User::class, 'remarks_updated_by');
    }

    public function latestUnitCost()
    {
        // whereNull(product_mapping_id): a product's OWN cost excludes blind
        // per-mapping blended rows (those belong to a parent+mapping, not the
        // product's intrinsic cost history). No effect on normal products.
        return $this->hasOne(UnitCost::class)->whereNull('product_mapping_id')->where('is_current', true)->orderBy('date_from', 'desc')->orderBy('created_at', 'desc');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function productLimits()
    {
        return $this->hasMany(ProductLimit::class);
    }

    public function productMovements()
    {
        return $this->hasMany(ProductMovement::class);
    }

    public function productSubCategory()
    {
        return $this->belongsTo(ProductSubCategory::class);
    }

    public function productUoms()
    {
        return $this->hasMany(ProductUom::class, 'product_id')->orderBy('value');
    }

    public function sellingPrices()
    {
        return $this->hasMany(SellingPrice::class)->orderBy('type', 'asc');
    }

    public function stockCountItems()
    {
        return $this->hasMany(StockCountItem::class);
    }

    public function tagBindings()
    {
        return $this->morphMany(TagBinding::class, 'modelable');
    }

    public function thumbnail()
    {
        return $this->morphOne(Attachment::class, 'modelable')->ofMany('type', 'min');
    }

    public function unitCosts()
    {
        // Intrinsic cost history only — blind per-mapping blended rows are kept
        // out (see latestUnitCost). No effect on normal products.
        return $this->hasMany(UnitCost::class)->whereNull('product_mapping_id')->orderBy('is_current', 'desc')->orderBy('date_from', 'desc')->orderBy('created_at', 'desc');
    }

    public function vendChannels()
    {
        return $this->hasMany(VendChannel::class);
    }

    // Blind SKU: flavours bound under THIS product (only when is_parent_sku).
    public function blindChildren()
    {
        return $this->hasMany(ProductChild::class, 'parent_product_id')->orderBy('sort');
    }

    public function activeBlindChildren()
    {
        return $this->hasMany(ProductChild::class, 'parent_product_id')
            ->where('is_active', true)
            ->orderBy('sort');
    }

    // Blind SKU: rows where THIS product is used as a child flavour under some
    // parent. Used to fan out blended-cost recompute when this product's cost
    // changes.
    public function blindParentLinks()
    {
        return $this->hasMany(ProductChild::class, 'child_product_id');
    }

    public function vendTransactions()
    {
        return $this->hasMany(VendTransaction::class);
    }

    // "Access Product(s)" inverse sides - who has been granted this SKU.
    public function accessUsers()
    {
        return $this->belongsToMany(User::class);
    }

    public function accessOperators()
    {
        return $this->belongsToMany(Operator::class);
    }

    public function opsJobItemChannels()
    {
        return $this->hasManyThrough(
            OpsJobItemChannel::class,
            VendChannel::class,
            'product_id',
            'vend_channel_id',
            'id',
            'id'
        );
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
        $request->merge([
            'is_active' => $request->is_active ? $request->is_active : true,
            'is_inventory' => $request->is_inventory ? $request->is_inventory : true,
            'operator_id' => $request->operator_id ? $request->operator_id : auth()->user()->operator_id,
        ]);

        return $query->when($request->has('visited'), function ($query, $search) use ($request) {
            if ($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            } else {
                $query->whereRaw('1 = 0');
            }
        })
            ->when($request->codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = explode(',', $search);
                } else {
                    $search = [$search];
                }
                $query->whereHas('vendChannels.vend', function ($query) use ($search) {
                    $query->whereIn('code', $search);
                });
            })
            ->when($request->channel_codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = explode(',', $search);
                } else {
                    $search = [$search];
                }
                $query->whereHas('vendChannels', function ($query) use ($search) {
                    $query->whereIn('code', $search);
                });
            })
            ->when($request->customer_code, function ($query, $search) {
                $query->whereHas('vendChannels.vend.customer', function ($query) use ($search) {
                    $query->where('code', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->customer_name, function ($query, $search) {
                $query->whereHas('vendChannels.vend.customer', function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%{$search}%");
                })
                    ->orWhereHas('vendChannels.vend', function ($query) use ($search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    });
            })
            ->when($request->categories, function ($query, $search) {
                $query->whereHas('vendChannels.vend.customer.category', function ($query) use ($search) {
                    $query->whereIn('id', $search);
                });
            })
            ->when($request->categoryGroups, function ($query, $search) {
                $query->whereHas('vendChannels.vend.customer.category.categoryGroup', function ($query) use ($search) {
                    $query->whereIn('id', $search);
                });
            })
            ->when($request->is_binded_customer, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->has('vendChannels.vend.customer');
                    } else {
                        $query->doesntHave('vendChannels.vend.customer');
                    }
                }
            })
            ->when($request->location_type_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->whereHas('vendChannels.vend.customer', function ($query) use ($search) {
                        $query->where('location_type_id', $search);
                    });
                }
            })
            ->when($request->operator_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->whereHas('operator', function ($query) use ($search) {
                        $query->where('operators.id', $search);
                    });
                }
            })
            ->when($request->code, function ($query, $search) {
                $query->where('code', 'LIKE', "%{$search}%");
            })
            ->when($request->name, function ($query, $search) {
                $query->where('name', 'LIKE', "%{$search}%");
            })
            ->when($request->is_active, function ($query, $search) {
                $query->where('is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->is_inventory, function ($query, $search) {
                $query->where('is_inventory', filter_var($search, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->is_comm_or_sf, function ($query, $search) {
                switch ($search) {
                    case 'comm':
                        $query->where('is_commission', 1)->where('is_supermarket_fee', 0);
                        break;
                    case 'sf':
                        $query->where('is_commission', 0)->where('is_supermarket_fee', 1);
                        break;
                    case 'both':
                        $query->where(function ($query) {
                            $query->where('is_commission', 1)->orWhere('is_supermarket_fee', 1);
                        });
                        break;
                }
            })
            ->when($request->sortKey, function ($query, $search) use ($request) {
                if (strpos($search, '->')) {
                    $inputSearch = explode('->', $search);
                    // C3: whitelist identifier chars before raw interpolation (no-op for valid sort keys)
                    $inputSearch[0] = preg_replace('/[^A-Za-z0-9_]/', '', $inputSearch[0] ?? '');
                    $inputSearch[1] = preg_replace('/[^A-Za-z0-9_]/', '', $inputSearch[1] ?? '');
                    $query->orderByRaw('LENGTH(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")))'.(filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                        ->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                } else {
                    $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                }
            });
    }

    // ── Warehouse qty source (design §8.1) ─────────────────────────────────

    public function warehouseQtySource(): \App\Enums\WarehouseQtySource
    {
        return \App\Enums\WarehouseQtySource::tryFrom((string) $this->warehouse_qty_source)
            ?? \App\Enums\WarehouseQtySource::Cms;
    }

    public function usesLedgerWarehouseQty(): bool
    {
        return $this->warehouseQtySource() === \App\Enums\WarehouseQtySource::Ledger;
    }

    /**
     * Latest self-system stock-in per product row, as `last_incoming_qty` and
     * `last_incoming_at` (the movement's user-keyed created_at). Incoming type
     * only — adjustments are corrections, not deliveries. Both Warehouse Qty
     * tabs show it under the warehouse figure; cms-source rows on the API tab
     * get overwritten with CMS's own last Stock In batch.
     */
    public function scopeWithLastLedgerIncoming($query)
    {
        $latest = fn (string $column) => function ($sub) use ($column) {
            $sub->from('product_movements')
                ->select($column)
                ->whereColumn('product_movements.product_id', 'products.id')
                ->where('type', ProductMovement::TYPE_INCOMING)
                ->where('qty', '>', 0)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(1);
        };

        return $query
            ->selectSub($latest('qty'), 'last_incoming_qty')
            ->selectSub($latest('created_at'), 'last_incoming_at');
    }

    /** CityBox SKUs linked to this product (many-to-one: their catalog has duplicate names). */
    public function cityboxProducts()
    {
        return $this->hasMany(CityboxProduct::class);
    }

    /**
     * mark1-ledger warehouse quantity: incoming/adjustment movements minus
     * picks (the same arithmetic /products/movements shows). ONE query pair,
     * used only for ledger-source products; cms-source products keep reading
     * the CMS API in ProductController::index — this accessor is the single
     * place that knows the branch, so chiller pick screens (step 5/6) call it
     * and never re-implement the rule.
     */
    public function warehouseQty(): ?int
    {
        if (! $this->usesLedgerWarehouseQty()) {
            return null; // cms-source: caller reads the CMS figure it already has
        }
        // MUST stay identical to ProductMovementController::getProductQuery's
        // total_movements_qty / total_delivered_qty subselects (the page and this
        // accessor are the same number by contract): incoming+adjustment only;
        // picks from picked-or-later, not cancelled, jobs dated on/after the
        // ledger go-live 2025-12-06.
        $moved = (int) \Illuminate\Support\Facades\DB::table('product_movements')
            ->where('product_id', $this->id)
            ->whereIn('type', [ProductMovement::TYPE_INCOMING, ProductMovement::TYPE_ADJUSTMENT])
            ->sum('qty');
        $picked = (int) \Illuminate\Support\Facades\DB::table('ops_job_item_channels')
            ->join('ops_job_items', 'ops_job_items.id', '=', 'ops_job_item_channels.ops_job_item_id')
            ->join('ops_jobs', 'ops_jobs.id', '=', 'ops_job_items.ops_job_id')
            ->where('ops_job_item_channels.product_id', $this->id)
            ->where('ops_job_items.status', '>=', 2)
            ->where('ops_job_items.status', '!=', 99)
            ->where('ops_jobs.date', '>=', self::LEDGER_GO_LIVE_DATE)
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(ops_job_item_channels.picked_qty, 0)'));

        return $moved - $picked;
    }

    /**
     * Units of this product currently inside Smart Chillers (sum of
     * vend_channels.qty across smart_chiller vends), from the 3-min CityBox
     * poll. Warehouse + in-chillers = total on hand for a chiller product.
     * One aggregate query; call only where it is displayed.
     */
    public function qtyInChillers(): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('vend_channels')
            ->join('vends', 'vends.id', '=', 'vend_channels.vend_id')
            ->where('vend_channels.product_id', $this->id)
            ->where('vend_channels.is_active', true)
            ->where('vends.machine_type', Vend::MACHINE_TYPE_SMART_CHILLER)
            ->sum('vend_channels.qty');
    }

    /** Ledger picks are counted from this date (matches ProductMovementController). */
    public const LEDGER_GO_LIVE_DATE = '2025-12-06';
}
