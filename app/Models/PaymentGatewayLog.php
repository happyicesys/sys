<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
     * @param  \Illuminate\Http\Request  $request
     * @param  int[]  $testingVendIds   Testing machine vend IDs to exclude.
     * @param  bool   $applyCutoff      Apply the UNREPORTED_GATEWAY_CUTOFF floor.
     */
    public function scopeUnreportedDispensed($query, $request, array $testingVendIds = [], bool $applyCutoff = true)
    {
        return $query
            ->where('payment_gateway_logs.status', self::STATUS_APPROVE)
            ->where('payment_gateway_logs.is_dispensed', true)
            ->whereDoesntHave('vendTransaction')
            ->when(!empty($testingVendIds), fn($q) => $q->whereNotIn('payment_gateway_logs.vend_id', $testingVendIds))
            ->when($request->operators, function ($q) use ($request) {
                $ops = (array) $request->operators;
                if (!in_array('all', $ops, true)) {
                    $q->whereHas('operatorPaymentGateway', fn($sub) => $sub->whereIn('operator_id', $ops));
                }
            })
            ->when($request->date_from, fn($q, $search) => $q->where('payment_gateway_logs.approved_at', '>=', $search))
            ->when($request->date_to, fn($q, $search) => $q->where('payment_gateway_logs.approved_at', '<=', $search))
            ->when($request->codes, function ($q, $search) {
                if (strpos($search, ',') !== false) {
                    $codes = array_map('trim', explode(',', $search));
                    $q->whereHas('vend', fn($sub) => $sub->whereIn('code', $codes));
                } else {
                    $q->whereHas('vend', fn($sub) => $sub->where('code', 'LIKE', "%{$search}%"));
                }
            })
            ->when($request->customer, function ($q, $search) {
                if (strpos($search, '-')) {
                    $parts = explode('-', $search, 2);
                    $q->whereHas('vend.customer', fn($sub) => $sub
                        ->where('virtual_customer_prefix', $parts[0])
                        ->where('virtual_customer_code', $parts[1]));
                } else {
                    $q->whereHas('vend.customer', fn($sub) => $sub
                        ->where('virtual_customer_prefix', 'LIKE', "{$search}%")
                        ->orWhere('virtual_customer_code', 'LIKE', "{$search}%")
                        ->orWhere(DB::raw('lower(name)'), 'LIKE', '%' . strtolower($search) . '%'));
                }
            })
            ->when($applyCutoff, fn($q) => $q->where('payment_gateway_logs.approved_at', '>=', self::UNREPORTED_GATEWAY_CUTOFF));
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
                if (strpos($search, "-")) {
                    $searchArray = explode("-", $search);
                    $query->whereHas('vend.customer', function ($query) use ($searchArray) {
                        $query->where('virtual_customer_prefix', $searchArray[0])
                            ->where('virtual_customer_code', $searchArray[1]);
                    });
                } else {
                    $query->whereHas('vend.customer', function ($query) use ($search) {
                        $query->where('virtual_customer_prefix', 'LIKE', "{$search}%")
                            ->orWhere('virtual_customer_code', 'LIKE', "{$search}%")
                            ->orWhere(DB::raw("lower(name)"), 'LIKE', '%' . strtolower($search) . '%');
                    });
                }
            })
            ->when($request->operators, function ($query, $search) {
                if (!in_array('all', $search)) {
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
                    $inputSearch = explode("->", $search);
                    $query->orderByRaw('LENGTH(json_unquote(json_extract(`' . $inputSearch[0] . '`, "$.' . $inputSearch[1] . '")))' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                        ->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                } else {
                    $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                }
            });

        return $query;
    }
}
