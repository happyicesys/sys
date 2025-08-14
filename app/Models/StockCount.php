<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class StockCount extends Model
{
    protected $fillable = [
        'cash_sales_amount',
        'cashless_sales_amount',
        'coin_float_amount',
        'customer_id',
        'day',
        'location_type_id',
        'month',
        'operator_id',
        'product_mapping_id',
        'vend_id',
        'vend_contract_id',
        'vend_model_id',
        'vend_prefix_id',
        'year',
    ];

    // mutator and accessor
    protected function cashSalesAmount(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value/ 100 ,
        );
    }

    protected function cashlessSalesAmount(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value/ 100 ,
        );
    }

    protected function coinFloatAmount(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value/ 100 ,
        );
    }

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

    public function productMapping()
    {
        return $this->belongsTo(ProductMapping::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendContract()
    {
        return $this->belongsTo(VendContract::class);
    }

    public function vendModel()
    {
        return $this->belongsTo(VendModel::class);
    }

    public function vendPrefix()
    {
        return $this->belongsTo(VendPrefix::class);
    }

    public function stockCountItems()
    {
        return $this->hasMany(StockCountItem::class);
    }

    // scopes
    public function scopeFilterIndex($query, $request)
    {
        // ---- Date range via (year, month, day)
        $from = $request->date_from ?? null;
        $to   = $request->date_to   ?? null;

        if ($from && $to) {
            [$fy,$fm,$fd] = array_map('intval', explode('-', Carbon::parse($from)->toDateString()));
            [$ty,$tm,$td] = array_map('intval', explode('-', Carbon::parse($to)->toDateString()));
            $query->whereRaw('(year, month, day) BETWEEN (?, ?, ?) AND (?, ?, ?)', [$fy,$fm,$fd,$ty,$tm,$td]);
        } elseif ($from) {
            [$fy,$fm,$fd] = array_map('intval', explode('-', Carbon::parse($from)->toDateString()));
            $query->whereRaw('(year, month, day) >= (?, ?, ?)', [$fy,$fm,$fd]);
        } elseif ($to) {
            [$ty,$tm,$td] = array_map('intval', explode('-', Carbon::parse($to)->toDateString()));
            $query->whereRaw('(year, month, day) <= (?, ?, ?)', [$ty,$tm,$td]);
        }

        // ---- Location Type (id or array; 'all' bypass)
        $locationType = $request->location_type_id ?? $request->locationType ?? null;
        if (!is_null($locationType) && $locationType !== 'all') {
            is_array($locationType)
                ? $query->whereIn('location_type_id', $locationType)
                : $query->where('location_type_id', $locationType);
        }

        // ---- Operators (array; 'all' bypass)
        if ($request->operators && is_array($request->operators) && !in_array('all', $request->operators, true)) {
            $query->whereIn('operator_id', $request->operators);
        }

        // ---- Vend Prefixes (array; 'all' bypass)
        if ($request->vendPrefixes && is_array($request->vendPrefixes) && !in_array('all', $request->vendPrefixes, true)) {
            $query->whereIn('vend_prefix_id', $request->vendPrefixes);
        }

        // ---- Vend Codes (CSV or string; CSV -> exact IN, single -> LIKE)
        if ($request->codes) {
            $codes = is_string($request->codes)
                ? array_values(array_filter(array_map('trim', explode(',', $request->codes)), fn($v) => $v !== ''))
                : (array) $request->codes;

            if (count($codes) > 1) {
                $query->whereHas('vend', fn($q) => $q->whereIn('code', $codes));
            } elseif (count($codes) === 1) {
                $term = $codes[0];
                $query->whereHas('vend', fn($q) => $q->where('code', 'LIKE', "%{$term}%"));
            }
        }

        // ---- Products (ids array; 'all' bypass)
        if ($request->products && is_array($request->products) && !in_array('all', $request->products, true)) {
            $query->whereHas('stockCountItems', fn($q) => $q->whereIn('product_id', $request->products));
        }

        // ---- Parent sorting (ONLY for parent columns / JSON on parent)
        if ($request->sortKey) {
            $search = $request->sortKey;
            $dir    = filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc';

            // allow JSON path like column->key on parent table
            if (strpos($search, '->') !== false) {
                [$col, $jsonPath] = explode('->', $search, 2);
                $query->orderByRaw(
                    'LENGTH(JSON_UNQUOTE(JSON_EXTRACT(`'.$col.'`, "$.'.$jsonPath.'")))' . ' ' . $dir
                )->orderBy($search, $dir);
            } else {
                // guard: only order by real parent columns to avoid SQL errors
                $allowed = [
                    'id','customer_id','location_type_id','operator_id','product_mapping_id','vend_id',
                    'vend_code','vend_contract_id','vend_model_id','vend_prefix_id','day','month','year',
                    'created_at','updated_at',
                ];
                if (in_array($search, $allowed, true)) {
                    $query->orderBy($search, $dir);
                }
            }
        }

        return $query;
    }

}
