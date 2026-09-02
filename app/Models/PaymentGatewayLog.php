<?php

namespace App\Models;

use App\Support\SiteSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayLog extends Model
{
    const STATUS_PENDING = 1;

    const STATUS_APPROVE = 2;

    const STATUS_REFUND = 98;

    const STATUS_DECLINE = 99;

    const REFUND_PENDING_MINUTES = 10;

    // Accounting cutoff for dispensed-but-unreported gateway revenue (PayNow/QR
    // payments that were approved + dispensed but never reported back as a
    // vend_transaction). This revenue is only counted from this date onward —
    // both in the dashboard "Total Sales" headline and in the exported CSV — so
    // figures before this date continue to tally with prior accounting exports.
    const UNREPORTED_GATEWAY_CUTOFF = '2026-06-01';

    use HasFactory;

    protected $fillable = [
        'amount',
        'approved_at',
        'is_dispensed',
        'method',
        'request',
        'response',
        'order_id',
        'qr_ref_id',
        'qr_url',
        'qr_text',
        'operator_payment_gateway_id',
        'payment_gateway_id',
        'ref_id',
        'status',
        'txn_src',
        'vend_channels_json',
        'vend_code',
        'vend_id',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'is_dispensed' => 'boolean',
        'response' => 'json',
        'request' => 'json',
        'vend_channels_json' => 'json',
    ];

    public function operatorPaymentGateway()
    {
        return $this->belongsTo(OperatorPaymentGateway::class);
    }

    public function paymentGateway()
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendTransaction()
    {
        return $this->hasOne(VendTransaction::class);
    }

    /**
     * Dispensed-but-unreported gateway revenue for a transactions-index request.
     *
     * These are approved + dispensed PayNow/QR payments the machine never
     * reported back via TRADE, so no vend_transaction row exists for them. This
     * scope mirrors the filters used by the Total Sales headline so the dashboard
     * and the exported CSV count exactly the same rows.
     *
     * NOT every missing vend_transaction means the machine failed to report it:
     * RemoveOddTransactions (remove:today-odd-transactions, dailyAt 23:59) DELETES
     * transactions under the TEST operator or with a test-rig amount, which leaves
     * their gateway log looking exactly like an unreported one. Counting those
     * would re-add revenue that was deliberately swept — a machine under operator
     * TEST showed 0 rows in the table but a non-zero Total Sales. So the same
     * exclusion is mirrored here via excludeSweptOddTransactions().
     *
     * The two leading guards are the viewer boundary and are NOT optional - see
     * whereNoVendTransaction() and whereVisibleToViewer(). Everything after them
     * is a request filter, i.e. a preference that may only narrow what the
     * viewer is already entitled to. That is why the 'all' branch below is
     * allowed to drop the operator predicate: "all" means every operator the
     * viewer can see, and the ceiling has already been applied.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int[]  $testingVendIds  Testing machine vend IDs to exclude.
     * @param  bool  $applyCutoff  Apply the UNREPORTED_GATEWAY_CUTOFF floor.
     */
    public function scopeUnreportedDispensed($query, $request, array $testingVendIds = [], bool $applyCutoff = true)
    {
        return $query
            ->where('payment_gateway_logs.status', self::STATUS_APPROVE)
            ->where('payment_gateway_logs.is_dispensed', true)
            ->whereNoVendTransaction()
            ->whereVisibleToViewer()
            ->excludeSweptOddTransactions()
            ->when(! empty($testingVendIds), fn ($q) => $q->whereNotIn('payment_gateway_logs.vend_id', $testingVendIds))
            ->when($request->operators, function ($q) use ($request) {
                $ops = (array) $request->operators;
                if (! in_array('all', $ops, true)) {
                    $q->whereHas('operatorPaymentGateway', fn ($sub) => $sub->whereIn('operator_id', $ops));
                }
            })
            ->when($request->date_from, fn ($q, $search) => $q->where('payment_gateway_logs.approved_at', '>=', $search))
            ->when($request->date_to, fn ($q, $search) => $q->where('payment_gateway_logs.approved_at', '<=', $search))
            ->when($request->codes, function ($q, $search) {
                if (strpos($search, ',') !== false) {
                    $codes = array_map('trim', explode(',', $search));
                    $q->whereHas('vend', fn ($sub) => $sub->whereIn('code', $codes));
                } else {
                    $q->whereHas('vend', fn ($sub) => $sub->where('code', 'LIKE', "%{$search}%"));
                }
            })
            ->when($request->customer, function ($q, $search) {
                $q->whereHas('vend.customer', fn ($sub) => SiteSearch::for($search)->applyTo($sub));
            })
            ->when($applyCutoff, fn ($q) => $q->where('payment_gateway_logs.approved_at', '>=', self::UNREPORTED_GATEWAY_CUTOFF));
    }

    /**
     * Gateway logs the machine never reported back as a vend_transaction.
     *
     * MUST NOT be written as whereDoesntHave('vendTransaction'). VendTransaction
     * carries four global scopes (operator, per-user machine allow-list,
     * "Access Product(s)", "Transaction Access From"), and Eloquent applies them
     * INSIDE the relation-existence subquery. The test then silently degrades
     * from "no transaction exists" to "no transaction exists THAT I AM ALLOWED
     * TO SEE" - so every transaction belonging to another operator counted as
     * unreported, and its revenue was added to the viewer's Total Sales. An
     * operator with one machine and zero sales was shown the whole fleet's QR
     * revenue against an empty grid.
     *
     * Written as a raw correlated NOT EXISTS so no global scope can reach it.
     * VendTransaction does not use SoftDeletes, so this is otherwise identical
     * to the relation form.
     */
    public function scopeWhereNoVendTransaction($query)
    {
        return $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('vend_transactions')
                ->whereColumn('vend_transactions.payment_gateway_log_id', 'payment_gateway_logs.id');
        });
    }

    /**
     * Restrict to gateway logs on machines the viewer may see.
     *
     * payment_gateway_logs carries NO global scope of its own, and the request
     * filters in scopeUnreportedDispensed are a user PREFERENCE, not a ceiling -
     * selecting the "All" operator chip (the filter's default) drops the
     * operator predicate entirely. Without this, "All" means every operator in
     * the DATABASE rather than every operator the viewer may see.
     *
     * Deliberately expressed as existence of a visible `vend` rather than a
     * hand-written operator_id comparison: Vend carries OperatorVendFilterScope,
     * which Eloquent applies inside this subquery, so the boundary stays defined
     * in exactly one place and cannot drift from the machine/transaction grids.
     * This is load-bearing - it is not a redundant "does it have a vend" check.
     *
     * A log whose vend_id is null or dangling is excluded: revenue that cannot
     * be attributed to a machine must not be credited to whoever is looking.
     */
    public function scopeWhereVisibleToViewer($query)
    {
        return $query->whereHas('vend');
    }

    /**
     * Exclude gateway logs whose vend_transaction was (or would be) deleted by
     * RemoveOddTransactions, so swept revenue is never counted as
     * dispensed-but-unreported.
     *
     * Mirrors the job's predicate: operator TEST, or an amount in
     * VendTransaction::ODD_TRANSACTION_AMOUNTS. Those amounts are stored on
     * vend_transactions in MINOR units while payment_gateway_logs.amount is in
     * major units, so the log amount is scaled by the operator's country
     * currency_exponent before comparing.
     *
     * The job's ODD_TRANSACTION_RETAIN_PAYMENT_METHOD_CODES carve-out (Free Vend
     * / Remote Dispense, codes 10 & 11) is not replicated: gateway-backed
     * transactions always carry a payment-gateway payment method (code >= 101),
     * so the carve-out can never apply to these rows.
     *
     * Written as a correlated NOT EXISTS rather than a join so callers keep using
     * this scope with ->sum() / ->get() without column-ambiguity surprises.
     */
    public function scopeExcludeSweptOddTransactions($query)
    {
        $oddAmounts = VendTransaction::ODD_TRANSACTION_AMOUNTS;
        $placeholders = implode(', ', array_fill(0, count($oddAmounts), '?'));

        return $query->whereNotExists(function ($sub) use ($oddAmounts, $placeholders) {
            $sub->selectRaw('1')
                ->from('vends')
                ->join('operators', 'operators.id', '=', 'vends.operator_id')
                ->leftJoin('countries', 'countries.id', '=', 'operators.country_id')
                ->whereColumn('vends.id', 'payment_gateway_logs.vend_id')
                ->whereNotIn('vends.code', VendTransaction::ODD_TRANSACTION_RETAIN_VEND_CODES)
                ->where(function ($w) use ($oddAmounts, $placeholders) {
                    $w->where('operators.code', VendTransaction::ODD_TRANSACTION_OPERATOR_CODE)
                        ->orWhereRaw(
                            'ROUND(payment_gateway_logs.amount * POW(10, COALESCE(countries.currency_exponent, 2))) IN ('.$placeholders.')',
                            $oddAmounts
                        );
                });
        });
    }

    // scopes
    public function scopeFilterIndex($query, $request)
    {
        $query = $query->when($request->date_from, function ($query, $search) {
            $query->where('approved_at', '>=', $search);
        })
            ->when($request->date_to, function ($query, $search) {
                $query->where('approved_at', '<=', $search);
            })
            ->when($request->ref_id, function ($query, $search) {
                $query->where('ref_id', 'LIKE', "%{$search}%");
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
            ->when($request->is_dispensed, function ($query, $search) {
                if ($search != 'all') {
                    if (filter_var($search, FILTER_VALIDATE_BOOLEAN)) {
                        $query->where('is_dispensed', true);
                    } else {
                        $query->where('is_dispensed', false);
                    }
                }
            })
            ->when($request->is_found_in_transaction, function ($query, $search) {
                // "Found in transaction" = the machine reported the row via TRADE.
                // Under unified transactions a linked vend_transaction always
                // exists (pre-created at paid-time), so we key off the row's
                // is_found_in_transaction flag rather than mere relation existence.
                // Legacy rows default that flag to true, so behaviour is unchanged.
                if ($search != 'all') {
                    if (filter_var($search, FILTER_VALIDATE_BOOLEAN)) {
                        $query->whereHas('vendTransaction', function ($q) {
                            $q->where('is_found_in_transaction', true);
                        });
                    } else {
                        $query->where(function ($q) {
                            $q->doesntHave('vendTransaction')
                                ->orWhereHas('vendTransaction', function ($q2) {
                                    $q2->where('is_found_in_transaction', false);
                                });
                        });
                    }
                }
            })
            ->when($request->is_refunded, function ($query, $search) {
                if ($search != 'all') {
                    if (filter_var($search, FILTER_VALIDATE_BOOLEAN)) {
                        $query->where('status', 98);
                    } else {
                        $query->where('status', '<>', 98);
                    }
                }
            })
            ->when($request->order_id, function ($query, $search) {
                $query->where('payment_gateway_logs.order_id', 'LIKE', "{$search}%");
            })
            ->when($request->paymentMethod, function ($query, $search) {
                $query->where('payment_method_id', $search);
            })
            ->when($request->customer, function ($query, $search) {
                $query->whereHas('vend.customer', fn ($customer) => SiteSearch::for($search)->applyTo($customer));
            })
            ->when($request->operators, function ($query, $search) {
                if (! in_array('all', $search)) {
                    $query->whereHas('operatorPaymentGateway', function ($query) use ($search) {
                        $query->whereIn('operator_id', $search);
                    });
                }
            })
            ->when($request->payment_gateway_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('payment_gateway_id', $search);
                }
            })
            ->when($request->qr_ref_id, function ($query, $search) {
                $query->where('qr_ref_id', 'LIKE', "{$search}%");
            })
            // ->when($request->product_code, function($query, $search) {
            //     $query->where(function($query) use ($search) {
            //         $query->whereIn('vend_transactions.product_id', function($query) use ($search) {
            //             $query->select('id')->from('products')->where('code', 'LIKE', "{$search}%");
            //         });
            //         $query->orWhereHas('vendTransactionItems', function($query) use ($search) {
            //             $query->whereIn('product_id', function($query) use ($search) {
            //                 $query->select('id')->from('products')->where('code', 'LIKE', "{$search}%");
            //             });
            //         });
            //     });
            // })
            // ->when($request->product_name, function($query, $search) {
            //     $query->where(function($query) use ($search) {
            //         $query->whereIn('vend_transactions.product_id', function($query) use ($search) {
            //             $query->select('id')->from('products')->where('name', 'LIKE', "%{$search}%");
            //         });
            //         $query->orWhereHas('vendTransactionItems', function($query) use ($search) {
            //             $query->whereIn('product_id', function($query) use ($search) {
            //                 $query->select('id')->from('products')->where('name', 'LIKE', "%{$search}%");
            //             });
            //         });
            //     });
            // })
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

        return $query;
    }
}
