<?php

namespace App\Models;

use App\Models\Scopes\OperatorVendRecordScope;
use App\Models\Scopes\ProductAccessProductColumnScope;
use App\Models\Scopes\TransactionAccessScope;
use App\Support\SiteSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class VendProductRecord extends Model
{
    use HasFactory;

    protected $table = 'vend_product_records';

    protected static function booted()
    {
        // Reuse the same operator scoping as vend_records so multi-operator
        // users only see their own data by default.
        static::addGlobalScope(new OperatorVendRecordScope);

        // Product grain IS part of this table's key, so unlike vend_records it
        // can be filtered honestly by the "Access Product(s)" allow-list.
        static::addGlobalScope(new ProductAccessProductColumnScope);

        // "Transaction Access From" - same reasoning as vend_records. This is
        // what Dashboard > Performance (Lite) reads, so without it the Lite page
        // would happily show a date-restricted viewer their pre-cut-off history.
        static::addGlobalScope(new TransactionAccessScope('date'));
    }

    protected $fillable = [
        // Date dimensions
        'date',
        'day',
        'month',
        'monthname',
        'year',

        // Machine dimensions
        'vend_id',
        'vend_code',
        'vend_prefix_id',
        'vend_model_id',

        // Customer / Operator / Location
        'customer_id',
        'operator_id',
        'location_type_id',

        // Product dimensions (denormalised)
        'product_id',
        'product_code',
        'product_name',
        'category_id',
        'category_name',
        'category_group_id',
        'category_group_name',
        'product_sub_category_id',
        'product_sub_category_name',

        // Success metrics
        'total_amount',
        'total_count',
        'all_total_count',
        'revenue',
        'gross_profit',

        // Failure metrics
        'error_count',
        'failure_count',
        'failure_amount',

        // Online-channel metrics
        'online_success_amount',
        'online_success_count',
        'online_failure_amount',
        'online_failure_count',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function locationType()
    {
        return $this->belongsTo(LocationType::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendModel()
    {
        return $this->belongsTo(VendModel::class);
    }

    public function vendPrefix()
    {
        // withTrashed(): VendPrefix soft-deletes, and this product record's
        // vend_prefix_id is a historical snapshot — a retired prefix must still
        // resolve its name here instead of coming back null.
        return $this->belongsTo(VendPrefix::class)->withTrashed();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Filter scope mirroring VendRecord::scopeFilterIndex.
     *
     * Supports all existing dashboard filters (operators, codes, customer,
     * vendModels, vendPrefixes, locationType, categories, categoryGroups) PLUS
     * new product-level filters (product_id, product_codes, product_name,
     * category_id, category_group_id, product_sub_category_id).
     */
    public function scopeFilterIndex($query, $request)
    {
        return $query
            // ── visited guard (mirrors VendRecord) ───────────────────────────
            ->when($request->has('visited'), function ($query) use ($request) {
                if ($request->visited == 'true') {
                    $query->whereRaw('1 = 1');
                } else {
                    $query->whereRaw('1 = 0');
                }
            })

            // ── Machine code filter ───────────────────────────────────────────
            ->when($request->codes, function ($query, $search) use ($request) {
                if ($request->has('_resolved_vend_ids')) {
                    $query->whereIn('vend_product_records.vend_id', $request->input('_resolved_vend_ids', []));
                } else {
                    $codes = strpos($search, ',') !== false
                        ? array_map('trim', explode(',', $search))
                        : [$search];
                    $query->whereIn('vend_product_records.vend_id', function ($q) use ($codes) {
                        $q->select('id')->from('vends')->whereIn('code', $codes);
                    });
                }
            })

            // ── Customer binding filter ───────────────────────────────────────
            ->when($request->is_binded_customer, function ($query, $search) {
                if ($search !== 'all') {
                    if ($search === 'true') {
                        $query->whereNotNull('vend_product_records.customer_id');
                    } else {
                        $query->whereNull('vend_product_records.customer_id');
                    }
                }
            })

            // ── Customer (location) category filter ───────────────────────────
            ->when($request->categories, function ($query, $search) {
                $query->whereIn('vend_product_records.customer_id', function ($q) use ($search) {
                    $q->select('id')->from('customers')->whereIn('category_id', $search);
                });
            })

            // ── Customer category-group filter ────────────────────────────────
            ->when($request->categoryGroups, function ($query, $search) {
                $query->whereIn('vend_product_records.customer_id', function ($q) use ($search) {
                    $q->select('id')->from('customers')->whereIn('category_id', function ($q2) use ($search) {
                        $q2->select('id')->from('categories')->whereIn('category_group_id', $search);
                    });
                });
            })

            // ── Customer name / code search ───────────────────────────────────
            ->when($request->customer, function ($query, $search) {
                $query->whereIn('vend_product_records.customer_id', function ($q) use ($search) {
                    SiteSearch::for($search)->applyTo($q->select('id')->from('customers'));
                });
            })

            // ── Location type filter ──────────────────────────────────────────
            ->when($request->location_type_id, function ($query, $search) {
                if ($search !== 'all') {
                    $query->where('vend_product_records.location_type_id', $search);
                }
            })

            // ── Operator (single) filter ──────────────────────────────────────
            ->when($request->operator_id, function ($query, $search) {
                if ($search !== 'all') {
                    $query->where('vend_product_records.operator_id', $search);
                }
            })

            // ── Operators (multi) filter ──────────────────────────────────────
            ->when($request->operators, function ($query, $search) {
                if (! in_array('all', $search)) {
                    $query->whereIn('vend_product_records.operator_id', $search);
                }
            })

            // ── Vend model filter ─────────────────────────────────────────────
            ->when($request->vendModels, function ($query, $search) {
                $query->whereIn('vend_product_records.vend_model_id', $search);
            })

            // ── Vend prefix filter (with single-ud expansion) ─────────────────
            ->when($request->vendPrefixes, function ($query, $search) {
                if (in_array('single-ud', $search)) {
                    $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                    unset($search[array_search('single-ud', $search)]);
                }
                $query->whereIn('vend_product_records.vend_prefix_id', array_values($search));
            })

            // ── Product filters ───────────────────────────────────────────────
            ->tap(fn ($query) => static::applyProductFilters($query, $request));
    }

    /**
     * Request keys of the product filter row. Any one of them set means the
     * figures must be read per product, so Dashboard > Performance switches to
     * this table (DashboardController::show) when one arrives.
     */
    public const PRODUCT_FILTER_KEYS = [
        'product_id',
        'product_codes',
        'product_name',
        'product_category_id',
        'product_category_group_id',
        'product_sub_category_id',
    ];

    /** True when the request narrows to some products, not "every product". */
    public static function hasProductFilter($request): bool
    {
        foreach (self::PRODUCT_FILTER_KEYS as $key) {
            $value = $request->input($key);
            if (is_array($value)) {
                $value = array_filter($value, fn ($item) => $item !== null && $item !== '');
                if ($value && ! in_array('all', $value, true)) {
                    return true;
                }
            } elseif ($value !== null && $value !== '' && $value !== 'all') {
                return true;
            }
        }

        return false;
    }

    /**
     * The product filter row, for an Eloquent OR a raw builder on this table.
     *
     * One definition on purpose: the dashboard's "machines in operation" line is
     * a raw DB::table() query (no scope reaches it), and it read the whole fleet
     * while every other chart honoured the product filter.
     */
    public static function applyProductFilters($query, $request, string $table = 'vend_product_records')
    {
        return $query
            // Array OR scalar. The Dashboard > Performance (Lite) filter bar
            // posts these as arrays (MultiSelect, same as vendModels), while a
            // hand-built link may pass a single id — accept both rather than
            // TypeError on whichever the caller happens to send.
            ->when($request->product_id, function ($query, $search) use ($table) {
                self::whereProductColumn($query, $table.'.product_id', $search);
            })

            // ── Product code search (array, or comma-separated string) ─────────
            ->when($request->product_codes, function ($query, $search) use ($table) {
                if (! is_array($search)) {
                    $search = strpos($search, ',') !== false
                        ? array_map('trim', explode(',', $search))
                        : [$search];
                }
                $query->whereIn($table.'.product_code', $search);
            })

            // ── Product name search (partial) ─────────────────────────────────
            ->when($request->product_name, function ($query, $search) use ($table) {
                $query->where($table.'.product_name', 'LIKE', "%{$search}%");
            })

            // ── Product category filter ───────────────────────────────────────
            ->when($request->product_category_id, function ($query, $search) use ($table) {
                self::whereProductColumn($query, $table.'.category_id', $search);
            })

            // ── Product category-group filter ─────────────────────────────────
            ->when($request->product_category_group_id, function ($query, $search) use ($table) {
                self::whereProductColumn($query, $table.'.category_group_id', $search);
            })

            // ── Product sub-category filter ───────────────────────────────────
            ->when($request->product_sub_category_id, function ($query, $search) use ($table) {
                self::whereProductColumn($query, $table.'.product_sub_category_id', $search);
            });
    }

    /**
     * The products the filter row picks, as product ids, for the parts of the
     * dashboard that read live vend_transactions instead of this rollup (today's
     * bar, the top-10 pie). Same keys as applyProductFilters(), resolved against
     * the product master: today's rows carry no category snapshot yet, so the
     * product's current category is the only one there is. Sub-category is not a
     * products column, so it narrows the rollup only.
     *
     * @return int[]
     */
    public static function matchingProductIds($request): array
    {
        $query = DB::table('products')
            ->when($request->product_id, fn ($q, $search) => self::whereProductColumn($q, 'products.id', $search))
            ->when($request->product_codes, function ($q, $search) {
                if (! is_array($search)) {
                    $search = array_map('trim', explode(',', $search));
                }
                $q->whereIn('products.code', $search);
            })
            ->when($request->product_name, fn ($q, $search) => $q->where('products.name', 'LIKE', "%{$search}%"))
            ->when($request->product_category_id, fn ($q, $search) => self::whereProductColumn($q, 'products.category_id', $search))
            ->when($request->product_category_group_id, fn ($q, $search) => self::whereProductColumn($q, 'products.category_group_id', $search));

        return $query->pluck('products.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * whereIn for an array, where(=) for a scalar.
     *
     * `->when()` already skipped us for null/false/'' /[], so the only cases left
     * are a real scalar or a non-empty array. An array that arrives containing
     * the 'all' sentinel (the shape the other multi-select filters use) means
     * "no restriction", so add no predicate at all.
     */
    private static function whereProductColumn($query, string $column, $search): void
    {
        if (is_array($search)) {
            if (in_array('all', $search, true)) {
                return;
            }

            $query->whereIn($column, $search);

            return;
        }

        $query->where($column, $search);
    }
}
