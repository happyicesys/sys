<?php

namespace App\Models;

use App\Models\Scopes\OperatorTransactionFilterScope;
use App\Models\Scopes\OperatorUserTransactionFilterScope;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GetUserTimezone;

class VendTransaction extends Model
{
    use GetUserTimezone, HasFactory;

    const INTERFACE_TYPE_0 = 0;
    const INTERFACE_TYPE_1 = 1;

    const INTERFACE_TYPE_MAPPINGS = [
        self::INTERFACE_TYPE_0 => 'Normal',
        self::INTERFACE_TYPE_1 => 'Soft Keyboard/ Multiple Cart',
    ];

    // Settlement lifecycle for gateway-backed (pre-created) rows. Non-gateway /
    // legacy rows are always SETTLED (the column default), so existing sales
    // logic is unaffected. See PLAN_merge_payment_gateway_into_sales_transactions.md.
    const SETTLEMENT_PENDING = 0;   // paid, dispense outcome not yet known
    const SETTLEMENT_REFUNDED = 1;  // refunded / void — never counts as a sale
    const SETTLEMENT_SETTLED = 2;   // counts as a sale (normal error-code logic still applies)

    protected static function booted()
    {
        static::addGlobalScope(new OperatorTransactionFilterScope);
        static::addGlobalScope(new OperatorUserTransactionFilterScope);
    }

    protected $casts = [
        'items_json' => 'json',
        'label_json' => 'json',
        'location_type_json' => 'json',
        'meta_json' => 'json',
        'transaction_datetime' => 'datetime',
        'vend_transaction_json' => 'json',
        'is_zero_amount' => 'boolean',
        'is_found_in_transaction' => 'boolean',
        'settlement_status' => 'integer',
    ];

    protected $fillable = [
        'customer_id',
        'order_id',
        'transaction_datetime',
        'amount',
        'cashless_mfg',
        'is_zero_amount',
        'gross_profit',
        'gross_profit_margin',
        'gst_vat_rate',
        'interface_type',
        'is_multiple',
        'is_payment_received',
        'is_refunded',
        'items_json',
        'label_json',
        'location_type_id',
        'location_type_json',
        'meta_json',
        'operator_id',
        'payment_gateway_log_id',
        'payment_method_id',
        'product_id',
        'qty',
        'revenue',
        'success_qty',
        'dispensed_qty',
        'vend_channel_id',
        'vend_channel_code',
        'vend_channel_error_id',
        'vend_contract_id',
        'vend_model_id',
        'vend_prefix_id',
        'vend_id',
        'vend_transaction_json',
        'unit_cost',
        'unit_cost_id',
        'is_found_in_transaction',
        'settlement_status',
    ];

    protected $with = [
        'unitCost',
    ];

    // relationships
    public function getGrossProfit()
    {
        return $this->getRevenue() - $this->getUnitCost();
    }

    public function getRevenue()
    {
        return $this->amount / (1.00 + ($this->gst_vat_rate / 100));
    }

    public function getUnitCost()
    {
        return $this->unitCost ? $this->unitCost->cost * 100 : 0;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function deliveryPlatformOrder()
    {
        return $this->hasOne(DeliveryPlatformOrder::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function paymentGatewayLog()
    {
        return $this->belongsTo(PaymentGatewayLog::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unitCost()
    {
        return $this->belongsTo(UnitCost::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendChannel()
    {
        return $this->belongsTo(VendChannel::class);
    }

    public function vendChannelError()
    {
        return $this->belongsTo(VendChannelError::class);
    }

    public function vendChannelErrorLogs()
    {
        return $this->hasMany(VendChannelErrorLog::class)->latest();
    }

    public function vendTransactionItems()
    {
        return $this->hasMany(VendTransactionItem::class);
    }

    // scopes

    /**
     * Settlement gate shared by every sales/revenue aggregation.
     *
     * A row only counts toward sales when it is SETTLED. Legacy + non-gateway
     * rows default to SETTLED, so adding this gate is a no-op for existing data;
     * it only excludes the new gateway PENDING / REFUNDED rows. The normal
     * error-code success test (code IN (0,6) / NULL / is_multiple) is layered on
     * top of this by the caller — this gate does not replace it.
     *
     * WHERE-based callers: ->countsAsSale().
     * Raw CASE-based callers: VendTransaction::settledSql($alias) inside the CASE.
     */
    public function scopeCountsAsSale($query, string $alias = 'vend_transactions')
    {
        return $query->where($alias . '.settlement_status', self::SETTLEMENT_SETTLED);
    }

    /**
     * SQL fragment for the settlement gate, for injection into existing raw
     * CASE/SUM expressions. Centralises the magic value so no aggregator
     * hardcodes "settlement_status = 2".
     */
    public static function settledSql(string $alias = 'vend_transactions'): string
    {
        return $alias . '.settlement_status = ' . self::SETTLEMENT_SETTLED;
    }

    /**
     * Single source of truth for the transactions-index RAW totals aggregates.
     *
     * These are the additive fields the headline totals are built from — counts
     * and cent-amounts, BEFORE rate derivation and the unreported-gateway merge.
     * Used by transactions:rollup-daily (adds GROUP BY operator_id, date) and by
     * transactions:rollup-verify. Copied verbatim from the live totals query in
     * VendController so the rollup can never drift; the verify harness asserts it.
     *
     * Requires the same joins the live query uses: LEFT JOIN payment_methods,
     * vend_channel_errors, delivery_platform_orders.
     */
    public static function salesRawTotalsSelect(): array
    {
        return [
            DB::raw('CAST(COUNT(CASE
                WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true
                THEN 1 ELSE NULL END) AS SIGNED) AS success_count'),

            DB::raw('COUNT(*) AS total_count'),

            DB::raw('ROUND(COALESCE(SUM(CASE
                WHEN vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true
                THEN vend_transactions.amount ELSE 0 END), 0), 2) AS success_amount'),

            DB::raw('ROUND(COALESCE(SUM(CASE
                WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                    AND delivery_platform_orders.id IS NULL
                    AND payment_methods.code = 0
                THEN vend_transactions.amount ELSE 0 END), 0), 2) AS cash_amount'),

            DB::raw('ROUND(COALESCE(SUM(CASE
                WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                    AND delivery_platform_orders.id IS NULL
                    AND payment_methods.payment_gateway_id IS NULL
                    AND payment_methods.code > 0
                THEN vend_transactions.amount ELSE 0 END), 0), 2) AS cashless_terminal_amount'),

            DB::raw('ROUND(COALESCE(SUM(CASE
                WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                    AND delivery_platform_orders.id IS NULL
                    AND payment_methods.payment_gateway_id IS NOT NULL
                THEN vend_transactions.amount ELSE 0 END), 0), 2) AS qr_payment_amount'),

            DB::raw('CAST(COUNT(CASE
                WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                    AND delivery_platform_orders.id IS NULL
                    AND payment_methods.code = 0
                THEN 1 ELSE NULL END) AS SIGNED) AS cash_count'),

            DB::raw('CAST(COUNT(CASE
                WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                    AND delivery_platform_orders.id IS NULL
                    AND payment_methods.payment_gateway_id IS NULL
                    AND payment_methods.code > 0
                THEN 1 ELSE NULL END) AS SIGNED) AS cashless_terminal_count'),

            DB::raw('CAST(COUNT(CASE
                WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                    AND delivery_platform_orders.id IS NULL
                    AND payment_methods.payment_gateway_id IS NOT NULL
                THEN 1 ELSE NULL END) AS SIGNED) AS qr_payment_count'),

            DB::raw('CAST(SUM(CASE WHEN is_multiple = 0 AND (vend_channel_errors.code IS NULL OR vend_channel_errors.code NOT IN (4, 5)) THEN 1 ELSE 0 END) AS SIGNED) as single_qty'),

            DB::raw('CAST(SUM(CASE
                WHEN is_multiple = 0 AND (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL)
                THEN 1 ELSE 0 END) AS SIGNED) as success_single_qty'),

            DB::raw('CAST(COUNT(CASE
                WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                    AND delivery_platform_orders.id IS NOT NULL
                THEN 1 ELSE NULL END) AS SIGNED) AS delivery_platform_success_count'),

            DB::raw('ROUND(COALESCE(SUM(CASE
                WHEN (vend_channel_errors.code = 0 OR vend_channel_errors.code = 6 OR vend_channel_errors.code IS NULL OR is_multiple = true)
                    AND delivery_platform_orders.id IS NOT NULL
                THEN vend_transactions.amount ELSE 0 END), 0), 2) AS delivery_platform_success_amount'),

            DB::raw('CAST(SUM(CASE
                WHEN is_multiple = 1 AND delivery_platform_orders.id IS NOT NULL
                THEN 1 ELSE 0 END) AS SIGNED) AS multiple_count_delivery_platform'),

            DB::raw('CAST(SUM(CASE
                WHEN is_multiple = 1 AND delivery_platform_orders.id IS NULL
                THEN 1 ELSE 0 END) AS SIGNED) AS multiple_count_machine'),
        ];
    }

    /**
     * Item-level counts for is_multiple transactions (the vend_transaction_items
     * join). Second source-of-truth pair used by the rollup + verify harness.
     * Requires: ->where('is_multiple', true)->leftJoin('vend_transaction_items', ...).
     */
    public static function salesItemTotalsSelect(): array
    {
        return [
            DB::raw('COUNT(CASE WHEN vend_transaction_items.id IS NOT NULL AND (vend_transaction_items.vend_channel_error_code IS NULL OR vend_transaction_items.vend_channel_error_code NOT IN (4, 5)) THEN 1 END) as total_items'),
            DB::raw('COUNT(CASE WHEN vend_transaction_items.id IS NOT NULL AND (vend_transaction_items.vend_channel_error_code IN (0,6) OR vend_transaction_items.vend_channel_error_code IS NULL) THEN 1 END) as success_items'),
        ];
    }

    public function scopeFilterTransactionIndex($query, $request, $skipSort = false)
    {
        $isPaymentReceived = $request->is_payment_received != null ? $request->is_payment_received : 'all';

        $query = $query->when($request->date_from, function ($query, $search) {
            $query->where('vend_transactions.transaction_datetime', '>=', $search);
        })
            ->when($request->date_to, function ($query, $search) {
                $query->where('vend_transactions.transaction_datetime', '<=', $search);
            });

        $query->when($request->has('visited'), function ($query, $search) use ($request) {
            if ($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            } else {
                $query->whereRaw('1 = 0');
            }
        })
            ->when($request->apk_ver, function ($query, $search) {
                $query->where('vend_transactions.meta_json->apk_ver', 'LIKE', "%{$search}%");
            })
            // The standalone 'Card Terminal' filter was merged into the
            // 'Payment Method' filter on 2026-05-16. Credit-card transactions
            // are now narrowed by selecting "Credit Card (<terminal name>)"
            // options in `paymentMethods`, which the block below decodes.
            // The legacy `cashless_mfg` query param is intentionally no
            // longer honored — old saved URLs will degrade to "all".
            ->when($request->codes, function ($query, $search) use ($request) {
                // Use pre-resolved IDs when available (set by DashboardController) to skip the subquery.
                if ($request->has('_resolved_vend_ids')) {
                    $query->whereIn('vend_transactions.vend_id', $request->input('_resolved_vend_ids', []));
                } elseif (strpos($search, ',') !== false) {
                    $search = array_map('trim', explode(',', $search));
                    // Use whereIn subquery instead of whereHas to avoid correlated EXISTS subquery
                    $query->whereIn('vend_transactions.vend_id', function ($q) use ($search) {
                        $q->select('id')->from('vends')->whereIn('code', $search);
                    });
                } else {
                    // Keep LIKE for non-dashboard callers that may expect partial matching.
                    // Dashboard always hits the pre-resolved-IDs branch above.
                    $query->whereIn('vend_transactions.vend_id', function ($q) use ($search) {
                        $q->select('id')->from('vends')->where('code', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->when($request->channel_codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = array_map('trim', explode(',', $search));
                } else {
                    $search = [$search];
                }
                $query->where(function ($query) use ($search) {
                    $query->whereIn('vend_transactions.vend_channel_code', $search)
                        ->orWhereHas('vendTransactionItems', function ($query) use ($search) {
                            $query->whereIn('vend_channel_code', $search);
                        });
                });
            })
            ->when($request->errors, function ($query, $search) {
                if (in_array('errors_only', $search)) {
                    $query->where(function ($query) {
                        $query->whereHas('vendChannelError', function ($q) {
                            $q->whereNotIn('code', [0]);
                        })
                            ->orWhereHas('vendTransactionItems.vendChannelError', function ($q) {
                                $q->whereNotIn('code', [0]);
                            });
                    });
                } else if (in_array('1', $search)) {
                    $query->where(function ($query) {
                        $query->whereHas('vendChannelError', function ($query) {
                            $query->where('id', 1);
                        })->orWhereDoesntHave('vendChannelError');
                    });
                } else {
                    $query->where(function ($query) use ($search) {
                        $query->whereHas('vendChannelError', function ($query) use ($search) {
                            $query->whereIn('id', $search);
                        });

                        $query->orWhereHas('vendTransactionItems.vendChannelError', function ($query) use ($search) {
                            $query->whereIn('id', $search);
                        });
                    });
                }
            })
            ->when($request->hid_card_id, function ($query, $search) {
                $query->where('vend_transactions.meta_json->hid_card_id', 'LIKE', "%{$search}%");
            })
            ->when($request->has('interface_type'), function ($query, $search) use ($request) {
                if ($request->interface_type != 'all') {
                    $query->where('vend_transactions.interface_type', $request->interface_type);
                }
            })
            // ->where(function($query) use ($request) {
            //     if($request->interface_type != 'all') {
            //         $query->where('interface_type', $request->interface_type);
            //     }
            // })
            ->when($request->is_binded_customer, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->has('vend.customer');
                    } else {
                        $query->doesntHave('vend.customer');
                    }
                }
            })
            ->when($request->is_multiple, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->where('vend_transactions.is_multiple', true);
                    } else {
                        $query->where('vend_transactions.is_multiple', false);
                    }
                }
            })
            ->when($request->is_member, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->where('vend_transactions.vend_transaction_json->dcvend_user_id', '>', 0);
                    } else {
                        $query->where(function ($query) {
                            $query->where('vend_transactions.vend_transaction_json->dcvend_user_id', null)
                                ->orWhere('vend_transactions.vend_transaction_json->dcvend_user_id', 0);
                        });
                    }
                }
            })
            ->when($request->is_refunded, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->where('vend_transactions.is_refunded', true);
                    } else {
                        $query->where('vend_transactions.is_refunded', false);
                    }
                }
            })
            ->when($request->is_voucher, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->whereNotNull('meta_json->vouchers');
                    } else {
                        $query->whereNull('meta_json->vouchers');
                    }
                }
            })
            ->when($isPaymentReceived, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->where(function ($query) {
                            $query->where('vend_transactions.is_payment_received', true)
                                ->orWhereHas('vendTransactionItems.vendChannelError', function ($query) {
                                    $query->whereIn('code', [0, 6]);
                                });
                        });
                        // $query->where('is_payment_received', true);
                    } else {
                        $query->where(function ($query) {
                            $query->where('vend_transactions.is_payment_received', false)
                                ->orWhereHas('vendTransactionItems.vendChannelError', function ($query) {
                                    $query->whereNotIn('code', [0, 6]);
                                });
                        });
                    }
                }
            })
            ->when($request->member_code, function ($query, $search) {
                $query->where('vend_transactions.vend_transaction_json->dcvend_user_id', $search);
            })
            ->when($request->order_id, function ($query, $search) {
                $query->where('vend_transactions.order_id', 'LIKE', "%{$search}%");
            })
            ->when($request->paymentMethods, function ($query, $search) {
                // Values can be either:
                //   - a numeric PaymentMethod id (legacy behavior)
                //   - the string "cc:<terminal name>" — a synthetic option
                //     that means "Credit Card (payment_method.code = 1) AND
                //     vend_transactions.cashless_mfg = <terminal name>". The
                //     old "Card Terminal" dropdown was folded into this on
                //     2026-05-16; see paymentMethodOptions in
                //     resources/js/Pages/Vend/Transaction.vue.
                $values = array_values(array_filter((array) $search, fn($item) => $item !== 'all' && $item !== '' && $item !== null));
                if (empty($values)) {
                    return;
                }

                $numericIds = [];
                $terminalNames = [];
                foreach ($values as $v) {
                    if (is_string($v) && str_starts_with($v, 'cc:')) {
                        $name = substr($v, 3);
                        if ($name !== '') {
                            $terminalNames[] = $name;
                        }
                    } elseif (is_numeric($v)) {
                        $numericIds[] = (int) $v;
                    }
                }

                // Resolve Credit Card payment_method id once. Cached
                // for the request to avoid repeated lookups when the
                // scope is invoked multiple times in the same call
                // chain (e.g. totals + paginated rows).
                $creditCardId = !empty($terminalNames)
                    ? \Illuminate\Support\Facades\Cache::remember(
                        'payment_method_id_credit_card',
                        86400,
                        fn() => \App\Models\PaymentMethod::where('code', 1)->value('id')
                    )
                    : null;

                $query->where(function ($q) use ($numericIds, $terminalNames, $creditCardId) {
                    if (!empty($numericIds)) {
                        $q->orWhereIn('vend_transactions.payment_method_id', $numericIds);
                    }
                    if (!empty($terminalNames) && !empty($creditCardId)) {
                        $q->orWhere(function ($q2) use ($creditCardId, $terminalNames) {
                            $q2->where('vend_transactions.payment_method_id', $creditCardId)
                                ->whereIn('vend_transactions.cashless_mfg', $terminalNames);
                        });
                    }
                });
            })
            ->when($request->categories, function ($query, $search) {
                $query->whereIn('vend_transactions.customer_id', function ($query) use ($search) {
                    $query->select('id')->from('customers')->whereIn('category_id', $search);
                });
            })
            ->when($request->categoryGroups, function ($query, $search) {
                $query->whereIn('vend_transactions.customer_id', function ($query) use ($search) {
                    $query->select('id')->from('customers')->whereIn('category_id', function ($query) use ($search) {
                        $query->select('id')->from('categories')->whereIn('category_group_id', $search);
                    });
                });
            })
            ->when($request->customer, function ($query, $search) {
                if (strpos($search, "-")) {
                    $searchArray = explode("-", $search);
                    $query->whereIn('vend_transactions.customer_id', function ($query) use ($searchArray) {
                        $query->select('id')->from('customers')
                            ->where('virtual_customer_prefix', $searchArray[0])
                            ->where('virtual_customer_code', $searchArray[1]);
                    });
                } else {
                    $query->whereIn('vend_transactions.customer_id', function ($query) use ($search) {
                        $query->select('id')->from('customers')->where(function ($query) use ($search) {
                            $query->where('virtual_customer_prefix', 'LIKE', "{$search}%")
                                ->orWhere('virtual_customer_code', 'LIKE', "{$search}%")
                                ->orWhere(DB::raw("lower(name)"), 'LIKE', '%' . strtolower($search) . '%');
                        });
                    });
                }
            })
            ->when($request->location_type_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vend_transactions.location_type_id', $search);
                    // $query->whereIn('vend_transactions.customer_id', function($query) use ($search) {
                    //     $query->select('id')->from('customers')->where('location_type_id', $search);
                    // });
                }
            })
            ->when($request->operator_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vend_transactions.operator_id', $search);
                }
            })
            ->when($request->operators, function ($query, $search) {
                $search = array_filter((array)$search, fn($item) => $item !== 'all');
                if (!empty($search)) {
                    $query->whereIn('vend_transactions.operator_id', $search);
                }
            })
            ->when($request->product_code, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->whereIn('vend_transactions.product_id', function ($query) use ($search) {
                        $query->select('id')->from('products')->where('code', 'LIKE', "{$search}%");
                    });
                    $query->orWhereHas('vendTransactionItems', function ($query) use ($search) {
                        $query->whereIn('product_id', function ($query) use ($search) {
                            $query->select('id')->from('products')->where('code', 'LIKE', "{$search}%");
                        });
                    });
                });
            })
            ->when($request->product_name, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->whereIn('vend_transactions.product_id', function ($query) use ($search) {
                        $query->select('id')->from('products')->where('name', 'LIKE', "%{$search}%");
                    });
                    $query->orWhereHas('vendTransactionItems', function ($query) use ($search) {
                        $query->whereIn('product_id', function ($query) use ($search) {
                            $query->select('id')->from('products')->where('name', 'LIKE', "%{$search}%");
                        });
                    });
                });
            })
            ->when($request->filled('tag') && $request->tag !== 'all', function ($q) use ($request) {
                if ($request->tag === 'any') {
                    // any label at all
                    $q->whereRaw('JSON_LENGTH(COALESCE(vend_transactions.label_json, JSON_ARRAY())) > 0');
                } else {
                    // Match transactions whose label_json contains either the tag id, name, or slug
                    // Prefer simple JSON contains, then fall back to JSON_TABLE join for cross-representation matching
                    $tag = trim((string) $request->tag);
                    $isNumeric = is_numeric($tag);

                    $q->where(function ($sub) use ($isNumeric, $tag) {
                        if ($isNumeric) {
                            // Direct id stored in JSON array
                            $sub->whereJsonContains('vend_transactions.label_json', (int) $tag);
                        } else {
                            // Direct name/slug stored in JSON array
                            $sub->whereJsonContains('vend_transactions.label_json', $tag);
                        }

                        // Cross-match via tags table (handles when JSON stores name but filter is id, or vice versa)
                        if ($isNumeric) {
                            $where = "t.id = ?";
                            $bindings = [(int) $tag];
                        } else {
                            $where = "t.name = ? OR t.slug = ?";
                            $bindings = [$tag, $tag];
                        }

                        $sub->orWhereRaw(
                            "EXISTS (\n" .
                            "  SELECT 1\n" .
                            "  FROM JSON_TABLE(\n" .
                            "         COALESCE(vend_transactions.label_json, JSON_ARRAY()),\n" .
                            "         '$[*]' COLUMNS(\n" .
                            "           tag_id BIGINT PATH '$',\n" .
                            "           tag_name VARCHAR(255) PATH '$'\n" .
                            "         )\n" .
                            "       ) jt\n" .
                            "  JOIN tags t ON (t.id = jt.tag_id OR t.name = jt.tag_name)\n" .
                            "  WHERE {$where}\n" .
                            ")",
                            $bindings
                        );
                    });
                }
            })
            ->when($request->vendContracts, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('vend_transactions.vend_contract_id', $search);
                    // $query->whereHas('vend', function($query) use ($search) {
                    //     $query->whereIn('vend_contract_id', $search);
                    // });
                }
            })
            ->when($request->vendModels, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('vend_transactions.vend_model_id', $search);
                    // $query->whereHas('vend', function($query) use ($search) {
                    //     $query->whereIn('vend_model_id', $search);
                    // });
                }
            })
            ->when($request->vendPrefixes, function ($query, $search) {
                if (!in_array('all', $search)) {
                    if (in_array('single-ud', $search)) {
                        $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                        unset($search[array_search('single-ud', $search)]);
                    }
                    $query->whereIn('vend_transactions.vend_prefix_id', $search);
                    // $query->whereHas('vend', function($query) use ($search) {
                    //     if(in_array('single-ud', $search)) {
                    //         $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                    //         unset($search[array_search('single-ud', $search)]);
                    //     }
                    //     $query->whereIn('vend_prefix_id', $search);
                    // });
                }
            });

        if (!$skipSort) {
            $query->when($request->sortKey, function ($query, $search) use ($request) {
                if (strpos($search, '->')) {
                    $inputSearch = explode("->", $search);
                    $query->orderByRaw('LENGTH(json_unquote(json_extract(`' . $inputSearch[0] . '`, "$.' . $inputSearch[1] . '")))' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                        ->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                } else {
                    $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                }
            });
        }

        return $query;
    }

    public function scopeFilterReport($query, $request)
    {
        $query->when($request->has('visited'), function ($query, $search) use ($request) {
            if ($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            } else {
                $query->whereRaw('1 = 0');
            }
        })
            ->when($request->codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = explode(',', $search);
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->whereIn('code', $search);
                    });
                } else {
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->where('code', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->when($request->customer_code, function ($query, $search) {
                $query->whereIn('customer_id', function ($query) use ($search) {
                    $query->select('id')->from('customers')->where('code', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->customer_name, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->whereIn('vend_transactions.customer_id', function ($query) use ($search) {
                        $query->select('id')->from('customers')->where('name', 'LIKE', "{$search}%");
                    });
                    $query->orWhereIn('vend_transactions.vend_id', function ($query) use ($search) {
                        $query->select('id')->from('vends')->where('name', 'LIKE', "{$search}%");
                    });
                });
            })
            ->when($request->is_binded_customer, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->has('vend.customer');
                    } else {
                        $query->doesntHave('vend.customer');
                    }
                }
            })
            ->when($request->is_multiple, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->where('is_multiple', true);
                    } else {
                        $query->where('is_multiple', false);
                    }
                }
            })
            ->when($request->is_refunded, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->where('vend_transactions.is_refunded', true);
                    } else {
                        $query->where('vend_transactions.is_refunded', false);
                    }
                }
            })
            ->when($request->location_type_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->whereIn('customer_id', function ($query) use ($search) {
                        $query->select('id')->from('customers')->where('location_type_id', $search);
                    });
                }
            })
            ->when($request->operator_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('operator_id', $search);
                }
            })
            ->when($request->categories, function ($query, $search) {
                $query->whereHas('vend.customer.category', function ($query) use ($search) {
                    $query->whereIn('id', $search);
                });
            })
            ->when($request->categoryGroups, function ($query, $search) {
                $query->whereHas('vend.customer.category.categoryGroup', function ($query) use ($search) {
                    $query->whereIn('id', $search);
                });
            })
            ->when($request->vendContracts, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereHas('vend', function ($query) use ($search) {
                        $query->whereIn('vend_contract_id', $search);
                    });
                }
            })
            ->when($request->vendPrefixes, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereHas('vend', function ($query) use ($search) {
                        if (in_array('single-ud', $search)) {
                            $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                            unset($search[array_search('single-ud', $search)]);
                        }
                        $query->whereIn('vend_prefix_id', $search);
                    });
                }
            });

        return $query;
    }

    public function scopeIsSuccessful($query)
    {
        return $query->where(function ($query) {
            $query->whereIn('vend_channel_error_id', [1, 5])
                ->orWhereNull('vend_channel_error_id')
                ->orWhere('vend_transaction_json->GET_TYPE', 1);
        })
            // Unified transactions: a PENDING/REFUNDED gateway row is never
            // "successful". No-op for legacy/non-gateway rows (default SETTLED).
            ->where('settlement_status', self::SETTLEMENT_SETTLED);
    }

    public function scopeIsFailure($query)
    {
        return $query->where(function ($query) {
            $query->whereNotNull('vend_channel_error_id')
                ->whereNotIn('vend_channel_error_id', [1, 5])
                ->orWhereNot('vend_transaction_json->GET_TYPE', 1);
        });
    }

    public function scopeIsError($query)
    {
        return $query->where(function ($query) {
            $query->where(function ($q) {
                $q->whereNotNull('vend_channel_error_id')
                    ->whereNotIn('vend_channel_error_id', [1])
                    ->orWhereNot('vend_transaction_json->GET_TYPE', 1);
            })->orWhereHas('vendTransactionItems', function ($q2) {
                $q2->whereNotNull('vend_channel_error_id')
                    ->whereNotIn('vend_channel_error_id', [1]);
            });
        });
    }
}
