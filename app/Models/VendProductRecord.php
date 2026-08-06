<?php

namespace App\Models;

use App\Models\Scopes\OperatorVendRecordScope;
use App\Models\Scopes\ProductAccessProductColumnScope;
use App\Models\Scopes\TransactionAccessScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
                    $q->select('id')
                        ->from('customers')
                        ->where('virtual_customer_prefix', 'LIKE', "{$search}%")
                        ->orWhere('virtual_customer_code', 'LIKE', "{$search}%")
                        ->orWhere('name', 'LIKE', "%{$search}%");
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
                if (!in_array('all', $search)) {
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

            // ── Product ID filter ─────────────────────────────────────────────
            // Array OR scalar. The Dashboard > Performance (Lite) filter bar
            // posts these as arrays (MultiSelect, same as vendModels), while a
            // hand-built link may pass a single id — accept both rather than
            // TypeError on whichever the caller happens to send.
            ->when($request->product_id, function ($query, $search) {
                $this->whereProductColumn($query, 'vend_product_records.product_id', $search);
            })

            // ── Product code search (array, or comma-separated string) ─────────
            ->when($request->product_codes, function ($query, $search) {
                if (! is_array($search)) {
                    $search = strpos($search, ',') !== false
                        ? array_map('trim', explode(',', $search))
                        : [$search];
                }
                $query->whereIn('vend_product_records.product_code', $search);
            })

            // ── Product name search (partial) ─────────────────────────────────
            ->when($request->product_name, function ($query, $search) {
                $query->where('vend_product_records.product_name', 'LIKE', "%{$search}%");
            })

            // ── Product category filter ───────────────────────────────────────
            ->when($request->product_category_id, function ($query, $search) {
                $this->whereProductColumn($query, 'vend_product_records.category_id', $search);
            })

            // ── Product category-group filter ─────────────────────────────────
            ->when($request->product_category_group_id, function ($query, $search) {
                $this->whereProductColumn($query, 'vend_product_records.category_group_id', $search);
            })

            // ── Product sub-category filter ───────────────────────────────────
            ->when($request->product_sub_category_id, function ($query, $search) {
                $this->whereProductColumn($query, 'vend_product_records.product_sub_category_id', $search);
            });
    }

    /**
     * whereIn for an array, where(=) for a scalar.
     *
     * `->when()` already skipped us for null/false/'' /[], so the only cases left
     * are a real scalar or a non-empty array. An array that arrives containing
     * the 'all' sentinel (the shape the other multi-select filters use) means
     * "no restriction", so add no predicate at all.
     */
    private function whereProductColumn($query, string $column, $search): void
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
