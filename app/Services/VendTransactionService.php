<?php

namespace App\Services;

use App\Jobs\HandleFailedVendTransaction;
use App\Jobs\SendDataToDcvend;
use App\Jobs\Vend\DecrementVendDailyStat;
use App\Jobs\Vend\SyncVendChannelErrorLog;
use App\Jobs\Vend\SyncVendTransactionTotalsJson;
use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGateways\Midtrans;
use App\Models\PaymentGateways\Omise;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Models\VendTransactionItem;
use Carbon\Carbon;
use DB;

class VendTransactionService
{
    /**
     * A cashless approval landing under this many ms after the APK armed the
     * request cannot be a fresh card tap (26–31s measured end-to-end) — it is
     * the VMC serving credit retained from an earlier failed vend. Mirrors the
     * SUSPECT_RETAINED_CREDIT threshold in mark1-apk ThreadForBrd; keep the
     * two in step.
     */
    public const CARD_APPROVAL_SUSPECT_MS = 5000;

    protected $voucherService;

    protected $paymentMethods;

    protected $vendChannelErrors;

    protected $vendChannels;

    protected $productMappingItems;

    public function __construct()
    {
        $this->voucherService = new VoucherService;
    }

    public function create(Vend $vend, $input, $isCurrentTime = true)
    {
        $vend->loadMissing([
            'customer.locationType',
            'customer.operator',
            'vendContract',
            'vendModel',
            'vendPrefix',
            'productMapping.productMappingItems.product.unitCosts',
        ]);

        $this->paymentMethods = PaymentMethod::all()->keyBy('code');
        $this->vendChannelErrors = VendChannelError::all()->keyBy('code');
        $this->vendChannels = $vend->vendChannels->keyBy('code');
        if ($vend->productMapping) {
            $this->productMappingItems = $vend->productMapping->productMappingItems->keyBy('channel_code');
        }

        $processedInput = $this->processMapping($vend, $this->processInput($vend, $input));

        DB::statement('SET innodb_lock_wait_timeout = 5'); // Prevent long waits
        DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');

        // Set true when this TRADE updates a gateway row that was pre-created at
        // paid-time (unified transactions), as opposed to a fresh insert. Drives
        // the post-transaction branching below.
        $wasPreCreatedUpdate = false;

        try {
            // 🔥 Store the result of the transaction
            $vendTransaction = DB::transaction(function () use ($processedInput, $vend, $isCurrentTime, &$wasPreCreatedUpdate) {
                if ($processedInput['interfaceType'] == '50') {
                    $processedInput['orderID'] = Carbon::now()->format('y').(Carbon::now()->format('m'))[0].$processedInput['orderID'];
                }

                // Look up an existing row for this order id (raw + TXN_SRC-50
                // prefixed form) with a row lock. Done for ALL vends so a
                // gateway pre-created row is always found (the 2007 dedup bypass
                // below only governs the genuine-duplicate short-circuit).
                $existingVendTransaction = VendTransaction::query()
                    ->where(function ($query) use ($processedInput) {
                        $query->where('order_id', $processedInput['orderID'])
                            ->orWhere('order_id', Carbon::now()->format('y').(Carbon::now()->format('m'))[0].$processedInput['orderID']);
                    })
                    ->where('vend_id', $vend->id)
                    ->lockForUpdate()
                    ->first();

                // Unified transactions: this row was pre-created at gateway
                // paid-time (is_found_in_transaction = false, linked to a PG log).
                // The machine's TRADE now fills it with ground truth instead of
                // creating a second row. Non-gateway flows never hit this branch.
                if ($existingVendTransaction
                    && ! $existingVendTransaction->is_found_in_transaction
                    && $existingVendTransaction->payment_gateway_log_id) {
                    $this->applyTradeToPreCreatedRow($existingVendTransaction, $vend, $processedInput);

                    if ($existingVendTransaction->amount > 0) {
                        $this->updateVendPaymentTimestamps(
                            $vend,
                            $existingVendTransaction->transaction_datetime instanceof Carbon
                                ? $existingVendTransaction->transaction_datetime->copy()
                                : Carbon::parse($existingVendTransaction->transaction_datetime),
                            $processedInput['paymentClassification'] ?? null,
                            $processedInput['interfaceType'] ?? null
                        );
                    }

                    if ($processedInput['vouchers']) {
                        foreach ($processedInput['vouchers'] as $voucher) {
                            $this->voucherService->updateUsedVoucher($voucher['code']);
                        }
                    }

                    $wasPreCreatedUpdate = true;

                    return $existingVendTransaction;
                }

                if ($vend->code != '2007') {
                    if ($existingVendTransaction) {
                        return null; // Exit and return null if duplicate exists
                    }

                    $shortVersionCreatedBefore = VendTransaction::query()
                        ->where('order_id', substr($processedInput['orderID'], 2))
                        ->where('vend_id', $vend->id)
                        ->lockForUpdate()
                        ->first();

                    if ($shortVersionCreatedBefore) {
                        $shortVersionCreatedBefore->delete();
                    }
                }

                // ✅ Create and return vend transaction

                $transaction = $this->createVendTransaction($vend, $processedInput, $isCurrentTime);

                if ($transaction && $transaction->amount > 0) {
                    $this->updateVendPaymentTimestamps(
                        $vend,
                        $transaction->transaction_datetime instanceof Carbon
                        ? $transaction->transaction_datetime->copy()
                        : Carbon::parse($transaction->transaction_datetime),
                        $processedInput['paymentClassification'] ?? null,
                        $processedInput['interfaceType'] ?? null
                    );
                }

                if ($processedInput['vouchers']) {
                    foreach ($processedInput['vouchers'] as $voucher) {
                        $this->voucherService->updateUsedVoucher($voucher['code']);
                    }
                }

                return $transaction;
            }, 3); // Retry up to 3 times

            if (! $vendTransaction) {
                return; // Prevent further execution if duplicate order ID
            }

            // Delivery-platform + PG-log linking only apply to the fresh-create
            // path. A pre-created gateway row was already linked at paid-time, and
            // its nofound_txn counter was never incremented (the row existed), so
            // there is nothing to decrement here.
            if (! $wasPreCreatedUpdate) {
                // store vend transaction id if found delivery platform order
                if ($deliveryPlatformOrder = DeliveryPlatformOrder::where('vend_transaction_order_id', $processedInput['orderID'])->first()) {
                    $deliveryPlatformOrder->update([
                        'vend_transaction_id' => $vendTransaction->id,
                        'status' => $deliveryPlatformOrder->status < DeliveryPlatformOrder::STATUS_DISPENSED ? DeliveryPlatformOrder::STATUS_DISPENSED : $deliveryPlatformOrder->status,
                        'status_json' => array_merge_recursive((array) $deliveryPlatformOrder->status_json, [
                            'status' => DeliveryPlatformOrder::STATUS_MAPPING[DeliveryPlatformOrder::STATUS_DISPENSED],
                            'datetime' => Carbon::now()->toDateTimeString(),
                        ]),
                        'dispensed_at' => Carbon::now(),
                    ]);
                }

                if ($paymentGatewayLog = PaymentGatewayLog::where('order_id', $vendTransaction->order_id)->first()) {
                    $vendTransaction->update([
                        'payment_gateway_log_id' => $paymentGatewayLog->id,
                    ]);

                    // "Found in Transactions?" just flipped false → true for this
                    // PG log. If the LogNofoundTxnIfStillMissing job already ran
                    // (i.e. >5 minutes have passed since approved_at), the +1 is
                    // already on vend_daily_stats and we need a matching -1 so the
                    // counter reflects only currently-unresolved anomalies.
                    // Under 5 minutes? The delayed log job hasn't fired yet — when
                    // it does, it'll re-check this PG log, see the txn linked,
                    // and no-op. Either way the counter ends up correct.
                    $approvedAt = $paymentGatewayLog->approved_at;
                    if ($approvedAt && $approvedAt->lt(Carbon::now()->subMinutes(5)) && $paymentGatewayLog->vend_id) {
                        DecrementVendDailyStat::dispatch(
                            (int) $paymentGatewayLog->vend_id,
                            'nofound_txn',
                            $approvedAt->copy()->toDateString()
                        )->onQueue('low');
                    }
                }
            } // end if (!$wasPreCreatedUpdate)

            // if($deliveryPlatformOrder = DeliveryPlatformOrder::where('vend_transaction_order_id', $processedInput['orderID'])->first()) {
            //     $deliveryPlatformOrder->update([
            //         'vend_transaction_id' => $vendTransaction->id,
            //     ]);
            // }

        } catch (\Exception $e) {
            \Log::error('Error creating vend transaction: '.$e->getMessage());

            return;
        }

        // ── Unified-transactions settle path ────────────────────────────────
        // The TRADE filled a pre-created gateway row. Items were already
        // rebuilt + the row settled inside the transaction. Fire the
        // once-at-settle dispatches here, then stop (the fresh-create
        // post-processing below must NOT run — it would re-create items).
        if ($wasPreCreatedUpdate) {
            SyncVendTransactionTotalsJson::dispatch($vend)->onQueue('default');

            // Hand to the refund/void path ONLY when the row ended up PENDING:
            // either nothing dispensed AND no dispense ACK, or a single-item
            // TRADE that reports a real dispense failure (resolvePreCreatedSettlement).
            // Already-REFUNDED rows and multi-item / successful TRADEs are
            // settled there, so this never refunds a confirmed sale or
            // double-handles an already-refunded row. For Omise this refunds
            // (RefundOmiseJob marks is_refunded + REFUNDED on success); for
            // non-Omise it is a no-op and the row stays PENDING (money held,
            // manual review).
            if ((int) $vendTransaction->settlement_status === VendTransaction::SETTLEMENT_PENDING) {
                HandleFailedVendTransaction::dispatch($vendTransaction)->onQueue('default');
            }

            // Channel-error logs from the machine result (mirror fresh-create).
            if (count($processedInput['children']) > 1) {
                foreach ($processedInput['children'] as $child) {
                    if (! empty($child['vendChannelErrorID'])) {
                        SyncVendChannelErrorLog::dispatch($vend, $child['vendChannelCode'], $child['errorCode'], $vendTransaction->id)->onQueue('default');
                    }
                }
            } else {
                if (! empty($processedInput['vendChannelErrorID'])) {
                    SyncVendChannelErrorLog::dispatch($vend, $processedInput['vendChannelCode'], $processedInput['errorCode'], $vendTransaction->id)->onQueue('default');
                }
            }

            // Operator transaction_upload callback — fire once, at settle.
            if ($vend->operator) {
                $callback = $vend->operator->operatorCallbacks()->where('code', 'transaction_upload')->first();
                if ($callback) {
                    $resource = new \App\Http\Resources\Callback\TransactionCallbackResource($vendTransaction);
                    $payload = $resource->resolve();
                    \App\Jobs\SendOperatorCallback::dispatch($callback->url, $payload)->onQueue('default');
                }
            }

            return;
        }

        // ✅ Use $vendTransaction safely outside the transaction
        if (! $processedInput['isSuccessful']) {
            HandleFailedVendTransaction::dispatch($vendTransaction)->onQueue('default');

            // Card terminal reversal: the reader already returned the money at
            // the machine — record it so no surface lets ops pay a second time.
            $this->markCardTerminalReversal($vendTransaction, $processedInput, $vend);
        }

        // Retained-credit settlement (2026-08-29): a card approval served in
        // under CARD_APPROVAL_SUSPECT_MS came from credit banked by an earlier
        // failed paid vend — no card was presented, no terminal settlement will
        // match. Runs for successful AND failed settlement vends (a successful
        // one consumes the credit; a failed one re-banks it and becomes the
        // next link in the chain). Card TRADEs never take the pre-created
        // gateway branch above, so this fresh-create hook covers every frame
        // that can carry the key.
        $this->recordRetainedCreditSettlement($vendTransaction, $processedInput);

        SyncVendTransactionTotalsJson::dispatch($vend)->onQueue('default');

        if ($vendTransaction) {
            // dd(sizeof($processedInput['children']), $processedInput['children']);
            if (count($processedInput['children']) > 1) {
                foreach ($processedInput['children'] as $child) {
                    $this->createVendTransactionItem($vendTransaction, $child);
                    if (! empty($child['vendChannelErrorID'])) {
                        SyncVendChannelErrorLog::dispatch($vend, $child['vendChannelCode'], $child['errorCode'], $vendTransaction->id)->onQueue('default');
                    }
                }
            } else {
                if (! empty($processedInput['vendChannelErrorID'])) {
                    SyncVendChannelErrorLog::dispatch($vend, $processedInput['vendChannelCode'], $processedInput['errorCode'], $vendTransaction->id)->onQueue('default');
                }
            }

        }

        if ($processedInput['dcvendUserID']) {
            SendDataToDcvend::dispatch($vendTransaction->id, $processedInput['dcvendUserID'])->onQueue('default');
        }

        // Send Callback
        if ($vendTransaction && $vend->operator) {
            $callback = $vend->operator->operatorCallbacks()->where('code', 'transaction_upload')->first();
            if ($callback) {
                // Use Resource for customizable payload
                $resource = new \App\Http\Resources\Callback\TransactionCallbackResource($vendTransaction);
                // Resolve resource to array
                $payload = $resource->resolve();

                \App\Jobs\SendOperatorCallback::dispatch($callback->url, $payload)->onQueue('default');
            }
        }
    }

    private function createVendTransaction($vend, $input, $isCurrentTime)
    {
        $customer = $vend->customer;
        $vendPrefix = $vend->vendPrefix;

        // Snapshot cashless manufacturer at the moment of the transaction so
        // historical reports stay accurate even after the vend's card
        // terminal is swapped. Only relevant for credit-card payments
        // (payment_method_id = 2); everything else stays null.
        //
        // Source: vends.card_terminal_id -> card_terminals.name (user-defined,
        // managed via the Card Terminal entity). Replaces the previous
        // unreliable read from acb_vmc_pa_json->CSHL_MFG (VM telemetry).
        $cashlessMfg = null;
        if ((int) $input['paymentMethodID'] === 2) {
            $cardTerminal = $vend->relationLoaded('cardTerminal')
                ? $vend->cardTerminal
                : $vend->cardTerminal()->first();

            $rawMfg = $cardTerminal->name ?? null;
            if (is_string($rawMfg)) {
                $rawMfg = trim($rawMfg);
            }
            $cashlessMfg = $rawMfg !== '' ? $rawMfg : null;
        }

        $vendTransaction = VendTransaction::create([
            'transaction_datetime' => $isCurrentTime ? Carbon::now() : Carbon::parse($input['time']),
            'amount' => $input['amount'],
            'cashless_mfg' => $cashlessMfg,
            'is_zero_amount' => $input['amount'] == 0,
            'order_id' => $input['orderID'],
            'interface_type' => $input['interfaceType'],
            'is_multiple' => $input['isMultiple'],
            'is_payment_received' => $input['isPaymentReceived'],
            'items_json' => $input['children'],
            'payment_method_id' => $input['paymentMethodID'],
            'qty' => $input['qty'],
            'success_qty' => $input['success_qty'],
            'dispensed_qty' => $input['dispensed_qty'],
            // Freeze the Product Drop Sensor state at the moment of the TRADE so
            // a later machine toggle never rewrites this record (Refund Index).
            'product_drop_sensor' => $vend->productDropSensorEnabled(),
            'vend_id' => $vend->id,
            'vend_channel_code' => $input['vendChannelCode'],
            'vend_contract_id' => $vend->vendContract?->id ?? null,
            'vend_channel_id' => $input['vendChannelID'],
            'vend_channel_error_id' => $input['vendChannelErrorID'],
            'vend_model_id' => $vend->vendModel?->id ?? null,
            'vend_prefix_id' => $vendPrefix?->id ?? null,
            'vend_transaction_json' => $input['originalJson'],
            'product_id' => $input['productID'],
            // Freeze the planogram in force at TRADE time — vends.product_mapping_id
            // is rewritten on every changeover, so without this the historical
            // product/price attribution is unrecoverable after a re-map.
            'product_mapping_id' => $input['productMappingID'] ?? null,
            'product_mapping_item_id' => $input['productMappingItemID'] ?? null,
            'customer_id' => $customer?->id ?? null,
            'location_type_id' => $customer?->locationType?->id ?? null,
            'operator_id' => $customer?->operator?->id ?? $vend->operator_id ?? 1,
            'unit_cost_id' => $input['unitCostID'],
            'gst_vat_rate' => $input['gstVatRate'],
            'revenue' => $revenue = $input['amount'] / (1.00 + ($input['gstVatRate'] / 100)),
            'unit_cost' => $unitCostValue = $input['unitCostValue'] ?? 0,
            'gross_profit' => $grossProfit = $revenue - $unitCostValue,
            'gross_profit_margin' => $revenue ? (($grossProfit * 100) / $revenue) : 0,
            'label_json' => isset($input['label']) ? $input['label'] : null,
            'meta_json' => [
                'apk_ver' => isset($vend->apk_ver_json['apkver']) ? $vend->apk_ver_json['apkver'] : null,
                'firmware_ver' => isset($vend->firmware_ver) ? dechex($vend->firmware_ver) : null,
                'vend_code' => $vend->code,
                'customer_code' => $customer ? ($customer->id + 20000) : null,
                'customer_name' => $customer?->name ?? null,
                // 'vend_prefix_id' => $vendPrefix?->id ?? null,
                'vend_prefix_name' => $vendPrefix?->name ?? null,
                'vouchers' => $input['vouchers'],
                'hid_card_id' => $input['hid_card_id'] ?? null,
            ],
        ]);

        return $vendTransaction;
    }

    /**
     * Fill a gateway row that was pre-created at paid-time with the machine's
     * TRADE ground truth. The amount stays the gateway-charged amount (money
     * actually collected); only the dispense outcome comes from the machine.
     * transaction_datetime is intentionally NOT moved (stays the paid time).
     *
     * Settlement is decided by resolvePreCreatedSettlement() — see there.
     */
    private function applyTradeToPreCreatedRow(VendTransaction $transaction, $vend, $input): void
    {
        $gstVatRate = $input['gstVatRate'];
        $amount = $transaction->amount; // keep the gateway-charged amount
        $unitCostValue = $input['unitCostValue'] ?? 0;
        $revenue = $amount / (1.00 + ($gstVatRate / 100));
        $grossProfit = $revenue - $unitCostValue;

        // Only a gateway we can refund by API may leave a failed single-item
        // vend PENDING for the refund path — for Fiuu/Midtrans instances
        // HandleFailedVendTransaction is a no-op, and a permanently-PENDING row
        // is dropped from sales AND never refunded, which is worse than the old
        // settled-but-unrefunded state. Non-refundable gateways keep the old
        // rule. PaymentGateway::supportsApiRefund() is the shared definition —
        // HandleFailedVendTransaction gates its dispatch on the same call.
        $gatewayRefundable = (bool) $transaction->paymentGatewayLog?->operatorPaymentGateway?->paymentGateway?->supportsApiRefund();

        $settlementStatus = self::resolvePreCreatedSettlement(
            (int) $transaction->settlement_status,
            (bool) ($transaction->paymentGatewayLog?->is_dispensed),
            $input,
            $gatewayRefundable
        );

        $transaction->forceFill([
            'interface_type' => $input['interfaceType'],
            'is_multiple' => $input['isMultiple'],
            'is_payment_received' => $input['isPaymentReceived'],
            'items_json' => $input['children'],
            'payment_method_id' => $input['paymentMethodID'] ?? $transaction->payment_method_id,
            'qty' => $input['qty'],
            'success_qty' => $input['success_qty'],
            'dispensed_qty' => $input['dispensed_qty'],
            // The TRADE is the transaction moment for a gateway row — freeze the
            // Product Drop Sensor state now (was null from the paid-time pre-create).
            'product_drop_sensor' => $vend->productDropSensorEnabled(),
            'vend_channel_code' => $input['vendChannelCode'],
            'vend_channel_id' => $input['vendChannelID'],
            'vend_channel_error_id' => $input['vendChannelErrorID'],
            'vend_transaction_json' => $input['originalJson'],
            'product_id' => $input['productID'],
            // The TRADE is the authoritative moment, so its planogram snapshot
            // wins — but never downgrade a value the paid-time pre-create already
            // captured to null (e.g. a TRADE whose channel no longer resolves).
            'product_mapping_id' => $input['productMappingID'] ?? $transaction->product_mapping_id,
            'product_mapping_item_id' => $input['productMappingItemID'] ?? $transaction->product_mapping_item_id,
            'unit_cost_id' => $input['unitCostID'],
            'unit_cost' => $unitCostValue,
            'gst_vat_rate' => $gstVatRate,
            'revenue' => $revenue,
            'gross_profit' => $grossProfit,
            'gross_profit_margin' => $revenue ? (($grossProfit * 100) / $revenue) : 0,
            'label_json' => $input['label'] ?? $transaction->label_json,
            'is_found_in_transaction' => true,
            // Resolved above — never demotes a confirmed dispense or resurrects a
            // refunded row.
            'settlement_status' => $settlementStatus,
        ])->save();

        // Rebuild multi-purchase items from the machine's children (delete +
        // recreate keeps it simple and correct vs. fuzzy per-channel matching).
        if (! empty($input['isMultiple']) && count($input['children']) > 0) {
            $transaction->vendTransactionItems()->delete();
            foreach ($input['children'] as $child) {
                $this->createVendTransactionItem($transaction, $child);
            }
        }
    }

    /**
     * Settlement state for a gateway row pre-created at paid-time, now that the
     * machine's TRADE has arrived. Pure (no DB) so it is unit-tested directly.
     *
     *   - already REFUNDED → stays REFUNDED (a late TRADE never revives a
     *     refunded row, e.g. after the 10-min no-dispense auto-refund).
     *   - single-item TRADE reporting a REAL dispense failure (no successful
     *     drop, error code not 0/6) → PENDING, **even if** the dispense ACK
     *     (GetPurchaseConfirm / payment_gateway_logs.is_dispensed) already
     *     settled the row. The ACK is sent by the APK when it RECEIVES the paid
     *     order — before the motor runs — so it is not proof of a drop and must
     *     not out-rank the machine's own verdict. The caller hands PENDING rows
     *     to HandleFailedVendTransaction → Omise refund → REFUNDED. This is the
     *     pre-2026-05-26 behaviour (every Omise single-purchase dispense error
     *     was auto-refunded) restored; see REFUND_INTEGRITY_AUDIT_2026-08-23.md.
     *     Only single-item purchases: a multi-item partial dispense is a sale.
     *   - otherwise: dispensed per TRADE, OR already SETTLED, OR ACKed → SETTLED.
     *   - else nothing dispensed and never confirmed → PENDING (refund/void path).
     *
     * @param  int  $current  current vend_transactions.settlement_status
     * @param  bool  $gatewayAcked  payment_gateway_logs.is_dispensed
     * @param  array  $input  processed TRADE (processMapping output)
     * @param  bool  $gatewayRefundable  the charge can be refunded by API (Omise).
     *                                   When false, a failed single-item vend is
     *                                   NOT demoted to PENDING — the refund path
     *                                   is a no-op there, and a forever-PENDING
     *                                   row would vanish from sales without ever
     *                                   being refunded (Fiuu/Midtrans instances).
     */
    public static function resolvePreCreatedSettlement(int $current, bool $gatewayAcked, array $input, bool $gatewayRefundable = true): int
    {
        if ($current === VendTransaction::SETTLEMENT_REFUNDED) {
            return VendTransaction::SETTLEMENT_REFUNDED;
        }

        if ($gatewayRefundable && self::isSingleItemDispenseFailure($input)) {
            return VendTransaction::SETTLEMENT_PENDING;
        }

        if (self::tradeDispensedAnything($input)
            || $current === VendTransaction::SETTLEMENT_SETTLED
            || $gatewayAcked) {
            return VendTransaction::SETTLEMENT_SETTLED;
        }

        return VendTransaction::SETTLEMENT_PENDING;
    }

    /**
     * A single-item TRADE whose machine verdict is "nothing successfully
     * dispensed": success_qty = 0 and a numeric error code outside the success
     * set {0, 6}. Codes 7/9 (sensor error) land here even though the motor ran
     * (dispensed_qty = 1) — ops treat them as a failed vend, the card terminals
     * reverse on them, and Omise used to auto-refund them. A non-numeric or
     * missing code is NOT a failure (never refund on a guess). Multi-item
     * purchases are never a "single-item failure".
     */
    public static function isSingleItemDispenseFailure(array $input): bool
    {
        if (! empty($input['isMultiple'])) {
            return false;
        }
        if ((int) ($input['success_qty'] ?? 0) > 0) {
            return false;
        }
        $code = $input['errorCode'] ?? null;
        if (! is_numeric($code)) {
            return false;
        }

        return ! in_array((int) $code, [0, 6], true);
    }

    /**
     * Card-terminal reversal footprint. On a SINGLE-item card vend that fails,
     * the VMC ends the MDB session with VEND FAILURE and the reader reverses the
     * charge at the machine (NETS shows "REVERSAL — Reversing The Previous
     * Transaction"; verified 2026-08-23 on the soft-keyboard flow). mark1 never
     * gets a processor callback — the only evidence is the machine's TRADE:
     *   PAY_TYPE = card, is_multiple = false, success_qty = 0,
     *   error code ∉ {0,6}, terminal ∈ config refund.card_reversal_terminals.
     *
     * The TRADE arrives in TWO shapes and ISOK only means something in one:
     *   - VMC-keypad flow (TXN_SRC = 0): flat VMC frame, header SErr, and
     *     ISOK = 0 on every failure (1:1 in prod) → require ISOK = 0 as a veto.
     *   - Soft-keyboard flow (TXN_SRC ≥ 1): the APK builds the TRADE itself and
     *     HARD-CODES ISOK = 1 (StaticFunction.mUploadTradeRet), error in
     *     transf_info[0].SErr — e.g. order 2026082415513017924 (Nets, SErr 4,
     *     ISOK 1). ISOK carries no signal there, so it is not consulted;
     *     err 7 instead needs per-trade proof of the fixed build (see
     *     isFixedBuildProof) or a machine reporting APK v303+ (v301
     *     retained-credit ambiguity — see below).
     * Cutting across BOTH shapes: v303+ frames carry CSHL_ARMED_MS
     * (arm→approval ms), and anything under CARD_APPROVAL_SUSPECT_MS vetoes
     * the claim outright — that approval was served from retained credit, so
     * there was no fresh auth and nothing for the reader to reverse.
     * Multi-item purchases are never reversed by the terminal (the session
     * covers several vends) — those stay a manual refund-ticket matter.
     *
     * @param  array  $input  processed TRADE (processMapping output)
     * @param  string|null  $cashlessMfg  vend_transactions.cashless_mfg snapshot
     */
    public static function isCardTerminalReversal(array $input, ?string $cashlessMfg, ?int $reportedApkVersion = null): bool
    {
        if (($input['paymentClassification'] ?? null) !== 'card') {
            return false;
        }
        if (! self::isSingleItemDispenseFailure($input)) {
            return false;
        }
        // Retained-credit veto, applied BEFORE the frame-shape split: v303+
        // big-board APKs stamp CSHL_ARMED_MS on the card TRADE (ms between
        // arming the cashless request and the VMC's approval). A genuine tap
        // needs a human plus reader auth (26–31s measured); an approval inside
        // 5s means the VMC satisfied the request from credit RETAINED by an
        // earlier failed vend (SUSPECT_RETAINED_CREDIT,
        // CARD_RETAINED_CREDIT_2026-08-22.md) — no fresh card auth happened,
        // so a failed dispense here has nothing for the reader to reverse.
        // That is a fact about the PAYMENT, not about which shape of frame
        // carried it, so it is not nested inside either branch: whatever
        // future path ever carries the key, the veto applies.
        $armedMs = self::cardApprovalArmedMs($input);
        if ($armedMs !== null && $armedMs < self::CARD_APPROVAL_SUSPECT_MS) {
            return false;
        }

        // VMC-originated frame → ISOK is the VMC's own trade-ok flag; a failure
        // TRADE always carries 0, so anything else vetoes the reversal claim.
        // Android-built TRADEs (interfaceType/TXN_SRC ≥ 1) hard-code ISOK = 1.
        if (empty($input['interfaceType'])) {
            $isok = $input['originalJson']['ISOK'] ?? null;
            if (! is_numeric($isok) || (int) $isok !== 0) {
                return false;
            }
        } elseif ((int) ($input['errorCode'] ?? 0) === 7
            && ! self::isFixedBuildProof($armedMs, $reportedApkVersion)
            && (int) $reportedApkVersion < 303) {
            // Soft-keyboard err 7 on APK ≤ v301 can be NETS *retaining* the
            // credit for a free re-vend (0x21 tradeId ownership bug, fixed in
            // big-board v303), not a reversal. Marking it refunded would claim
            // money moved that the customer may instead consume as a free
            // re-vend, and would auto-block a genuine refund claim
            // (RefundTicket::isAlreadyRefunded). So err 7 qualifies only with
            // per-trade proof of the fixed build (a well-formed CSHL_ARMED_MS
            // from a machine NOT on the small-board stream — see
            // isFixedBuildProof) or once the machine reports v303+
            // (Vend::reportedApkVersion); the version gate stays as the
            // fallback for v303 frames without the key and widens machine-by-
            // machine as the OTA lands. Small boards (13x stream) never reach
            // 303 and stay excluded until their own retained-credit fix is
            // field-verified. Other codes (e.g. SErr 4, order
            // 2026082415513017924) are field-verified reversals and pass
            // regardless of version.
            return false;
        }
        $terminals = (array) config('refund.card_reversal_terminals', []);

        return $cashlessMfg !== null && in_array($cashlessMfg, $terminals, true);
    }

    /**
     * The TRADE's CSHL_ARMED_MS (ms between the APK arming the cashless request
     * and the VMC's approval, stamped by big-board v303+ on Android-built card
     * frames), or null when absent or malformed — absence means a pre-v303
     * build, a VMC-keypad frame, or a card trade whose arm time was never
     * stamped, and every caller must treat those identically.
     */
    private static function cardApprovalArmedMs(array $input): ?int
    {
        $armedMs = $input['originalJson']['CSHL_ARMED_MS'] ?? null;

        return is_numeric($armedMs) && (int) $armedMs >= 0 ? (int) $armedMs : null;
    }

    /**
     * Does this TRADE prove, on its own, that it came from a build carrying the
     * 0x21 retained-credit fix?
     *
     * A well-formed CSHL_ARMED_MS is that proof TODAY because the key ships in
     * big-board v303, the same build as the fix. It stops being proof the
     * moment this plumbing is ported to mark1-apk-small, which shares the
     * codebase and the applicationId but is on the 13x versionCode stream and
     * has NOT had its own retained-credit fix field-verified — the key would
     * then appear on small-board frames and silently open the err-7 auto-refund
     * for them. So the proof is refused for any machine whose reported version
     * looks like the small-board stream; those stay on the v303+ version gate,
     * which they never satisfy, exactly as before.
     */
    private static function isFixedBuildProof(?int $armedMs, ?int $reportedApkVersion): bool
    {
        return $armedMs !== null && ! Vend::versionMaybeSmallBoardStream($reportedApkVersion);
    }

    /**
     * Record a card-terminal reversal on a freshly created card row:
     * is_refunded = true + auto_refund_source = card_terminal_reversal, then
     * cross any open refund ticket on it (markAutoRefundedByCharge pulls
     * approved/scheduled tickets out of payout — the same double-refund
     * guard the Omise job uses). Best-effort: a ticket-side failure must never
     * break TRADE ingestion.
     */
    private function markCardTerminalReversal(VendTransaction $vendTransaction, array $input, ?Vend $vend = null): void
    {
        if (! self::isCardTerminalReversal($input, $vendTransaction->cashless_mfg, $vend?->reportedApkVersion())) {
            return;
        }

        $vendTransaction->forceFill([
            'is_refunded' => true,
            'auto_refund_source' => \App\Support\AutoRefundSource::CARD_TERMINAL_REVERSAL,
        ])->save();

        try {
            app(\App\Services\Refund\RefundTicketService::class)
                ->markAutoRefundedByCharge($vendTransaction->order_id, null, $vendTransaction->id);
        } catch (\Throwable $e) {
            \Log::error('Refund ticket auto-resolve after card terminal reversal failed', [
                'vend_transaction_id' => $vendTransaction->id,
                'order_id' => $vendTransaction->order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Hand a suspect card trade to the settlement recorder. Best-effort and
     * isolated like markCardTerminalReversal: recording must never break
     * TRADE ingestion.
     */
    private function recordRetainedCreditSettlement(VendTransaction $vendTransaction, array $input): void
    {
        if (($input['paymentClassification'] ?? null) !== 'card') {
            return;
        }
        $armedMs = self::cardApprovalArmedMs($input);
        if ($armedMs === null || $armedMs >= self::CARD_APPROVAL_SUSPECT_MS) {
            return;
        }

        try {
            app(\App\Services\Refund\RetainedCreditSettlementRecorder::class)->record($vendTransaction);
        } catch (\Throwable $e) {
            \Log::error('Retained-credit settlement recording failed', [
                'vend_transaction_id' => $vendTransaction->id,
                'order_id' => $vendTransaction->order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Did the machine dispense anything for this TRADE? Covers single (qty 1)
     * and multi (summed). Used to decide sale vs. void for a settling row.
     */
    private static function tradeDispensedAnything(array $input): bool
    {
        return (int) ($input['dispensed_qty'] ?? 0) > 0
            || (int) ($input['success_qty'] ?? 0) > 0;
    }

    private function updateVendPaymentTimestamps(Vend $vend, Carbon $transactionTime, ?string $paymentClassification, $interfaceType = null): void
    {
        $attributes = [];

        if ($this->shouldUpdateVendTimestamp($vend->last_vend_transaction_at, $transactionTime)) {
            $attributes['last_vend_transaction_at'] = $transactionTime;
        }

        switch ($paymentClassification) {
            case 'cash':
                if ($this->shouldUpdateVendTimestamp($vend->last_cash_vend_transaction_at, $transactionTime)) {
                    $attributes['last_cash_vend_transaction_at'] = $transactionTime;
                }
                break;
            case 'card':
                if ($this->shouldUpdateVendTimestamp($vend->last_card_vend_transaction_at, $transactionTime)) {
                    $attributes['last_card_vend_transaction_at'] = $transactionTime;
                }
                break;
            case 'cashless':
                if ($this->shouldUpdateVendTimestamp($vend->last_cashless_vend_transaction_at, $transactionTime)) {
                    $attributes['last_cashless_vend_transaction_at'] = $transactionTime;
                }
                break;
        }

        if ($interfaceType == 1) {
            $attributes['is_txn_src'] = true;
            if ($this->shouldUpdateVendTimestamp($vend->last_txn_src_at, $transactionTime)) {
                $attributes['last_txn_src_at'] = $transactionTime;
            }
        }

        if (! empty($attributes)) {
            $vend->forceFill($attributes)->save();
        }
    }

    private function shouldUpdateVendTimestamp($currentValue, Carbon $candidate): bool
    {
        if (is_null($currentValue)) {
            return true;
        }

        $current = $currentValue instanceof Carbon ? $currentValue : Carbon::parse($currentValue);

        return $candidate->greaterThan($current);
    }

    private function createVendTransactionItem($vendTransaction, $input)
    {
        VendTransactionItem::create([
            'is_refunded' => false,
            'product_id' => $input['productID'],
            // Per-channel planogram row; this is where multi-purchase attribution
            // lives, since the parent carries no single mapping item.
            'product_mapping_item_id' => $input['productMappingItemID'] ?? null,
            'unit_cost_id' => $input['unitCostID'],
            'unit_cost' => $input['unitCostValue'] ?? 0,
            'unit_price_amount' => $input['unit_price_amount'] ?? 0,
            'vend_channel_id' => $input['vendChannelID'],
            'vend_channel_code' => $input['vendChannelCode'],
            'vend_channel_error_code' => $input['errorCode'],
            'vend_channel_error_id' => $input['vendChannelErrorID'],
            'vend_transaction_id' => $vendTransaction->id,
        ]);
    }

    private function createVendChannel($vendID, $channelCode)
    {
        // The caller's lookup ($this->vendChannels) is the FILTERED
        // vendChannels() relation (is_active AND capacity > 0), so a miss here
        // does not mean the row is absent — an inactive or zero-capacity
        // channel is invisible to it. firstOrCreate against the unfiltered
        // table reuses such a row instead of inserting a duplicate, which the
        // unique (vend_id, code) index would reject anyway.
        //
        // Knock-on: the returned row may now be a REAL channel carrying real
        // state rather than the blank one create() used to make, so
        // determineUnitPriceAmount's last-resort fallback can read that row's
        // amount / amount2. Reachable only when the item succeeded AND the
        // TRADE carried no unit_price_amount AND no positive amount — in which
        // case the deactivated channel's own price is a better answer than the
        // null the blank row produced. Covered by
        // VendChannelDuplicateGuardTest::test_trade_channel_creation_reuses_an_inactive_row.
        return VendChannel::firstOrCreate([
            'vend_id' => $vendID,
            'code' => (int) $channelCode,
        ]);
    }

    private function determineUnitPriceAmount(?VendChannel $vendChannel, array $input, bool $isSuccessfulItem): ?int
    {
        if (! $isSuccessfulItem) {
            return 0;
        }

        if (array_key_exists('unit_price_amount', $input) && ! is_null($input['unit_price_amount'])) {
            return (int) $input['unit_price_amount'];
        }

        // The machine's own reported amount wins whenever it is present and
        // positive, whether or not it matches either channel price — the two
        // branches this used to have both returned $amount.
        if (isset($input['amount']) && is_numeric($input['amount'])) {
            $amount = (int) round($input['amount']);
            if ($amount > 0) {
                return $amount;
            }
        }

        if ($vendChannel) {
            if (! is_null($vendChannel->amount) && $vendChannel->amount > 0) {
                return (int) $vendChannel->amount;
            }

            if (! is_null($vendChannel->amount2) && $vendChannel->amount2 > 0) {
                return (int) $vendChannel->amount2;
            }
        }

        return null;
    }

    private function extractChildAmountCents(array $trans): ?int
    {
        foreach (['Price', 'price', 'Amount', 'amount'] as $key) {
            if (! array_key_exists($key, $trans)) {
                continue;
            }

            $normalized = $this->normalizeAmountToCents($trans[$key]);
            if (! is_null($normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeAmountToCents($value): ?int
    {
        if (is_null($value)) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

        $stringValue = (string) $value;
        $numericValue = (float) $value;

        if (str_contains($stringValue, '.') || abs($numericValue - (int) $numericValue) > 0) {
            return (int) round($numericValue * 100);
        }

        return (int) $numericValue;
    }

    private function processMapping($vend, $input)
    {
        $gstVatRate = 0;
        $isPaymentReceived = false;
        $isSuccessful = false;
        $paymentMethod = (isset($input['paymentMethodCode']) && $this->paymentMethods) ? $this->paymentMethods->get($input['paymentMethodCode']) : null;
        $paymentClassification = null;

        if ($paymentMethod) {
            $paymentCode = (int) $paymentMethod->code;

            if ($paymentCode === 0) {
                $paymentClassification = 'cash';
            } elseif ($paymentCode === 1) {
                $paymentClassification = 'card';
            } elseif (! is_null($paymentMethod->payment_gateway_id)) {
                $paymentClassification = 'cashless';
            }
        }
        $product = null;
        $unitCostValue = 0;
        $unitCostId = null;
        $errorCode = $input['errorCode'] ?? 0;
        $vendChannelCode = $input['vendChannelCode'] ?? 0;

        $vendChannel = (isset($input['vendChannelCode']) && $this->vendChannels) ? $this->vendChannels->get($vendChannelCode) : null;
        $vendChannelError = (isset($input['errorCode']) && $this->vendChannelErrors) ? $this->vendChannelErrors->get($errorCode) : null;

        // hardcode when 0 and 6 error code means successful dispense
        if ($errorCode == '0' or $errorCode == '6') {
            $isPaymentReceived = true;
            $isSuccessful = true;
        }
        // 0, 7, 6, 9

        // handle those QR payment and grab mart, treat as payment received by default
        if ($paymentMethod) {
            if (isset(Midtrans::PAYMENT_METHOD_MAPPING[$paymentMethod->code]) or isset(Omise::PAYMENT_METHOD_MAPPING[$paymentMethod->code]) or $paymentMethod->code == Grab::PAYMENT_METHOD_GRABMART) {
                $isPaymentReceived = true;
            }
        }

        // Resolve the planogram item → product, then unit cost + gst rate.
        //
        // Smart freezers report no channel telemetry (the door never uploads
        // vend_channels; inventory qty is validated by the transaction + AI as a
        // separate logical layer). So for a smart mapping we resolve the product
        // straight from product_mapping_items by channel_code and NEVER create a
        // vend_channels row. Vending machines keep their existing vend_channel-
        // keyed path, untouched.
        $isSmart = $vend->productMapping && $vend->productMapping->is_smart;

        $productMappingItem = null;
        if ($isSmart) {
            $productMappingItem = ($this->productMappingItems && $vendChannelCode !== 0)
                ? $this->productMappingItems->get($vendChannelCode)
                : null;
        } elseif ($vendChannel && $this->productMappingItems) {
            $productMappingItem = $this->productMappingItems->get($vendChannel->code);
        }

        if ($productMappingItem) {
            $product = $productMappingItem->product;
            // For a blind parent, its current cost IS the derived blended cost
            // (one per product), so the normal resolution path covers both.
            $unitCost = $product->unitCosts->where('is_current', true)->first();
            if ($unitCost) {
                $unitCostId = $unitCost->id;
                $unitCostValue = $unitCost->cost * 100;
            }
            $gstVatRate = $product->operator ? $product->operator->gst_vat_rate : 0;
        }

        // handle not found vend channel (vending only — smart freezers never
        // create vend_channels rows)
        if (! $isSmart and ! $vendChannel and $vendChannelCode != 0) {
            $vendChannel = $this->createVendChannel($vend->id, $vendChannelCode);
        }

        $unitPriceAmount = $this->determineUnitPriceAmount($vendChannel, $input, $isSuccessful);

        return [
            'amount' => isset($input['amount']) ? $input['amount'] : 0,
            'children' => isset($input['children']) ? $input['children'] : [],
            'dcvendUserID' => isset($input['dcvendUserID']) ? $input['dcvendUserID'] : null,
            'dcvendDiscountAmount' => isset($input['dcvendDiscountAmount']) ? $input['dcvendDiscountAmount'] : null,
            'errorCode' => $errorCode,
            'gstVatRate' => $gstVatRate,
            'interfaceType' => isset($input['interfaceType']) ? $input['interfaceType'] : null,
            'isMultiple' => isset($input['isMultiple']) ? $input['isMultiple'] : false,
            'isPaymentReceived' => $isPaymentReceived,
            'isSuccessful' => $isSuccessful,
            'label' => isset($input['label']) ? $input['label'] : null,
            'orderID' => isset($input['orderID']) ? $input['orderID'] : null,
            'originalJson' => isset($input['originalJson']) ? $input['originalJson'] : null,
            // 'paymentGatewayLogID' => isset($paymentGatewayLog) ? $paymentGatewayLog->id : null,
            'paymentMethodCode' => isset($input['paymentMethodCode']) ? $input['paymentMethodCode'] : null,
            'paymentMethodID' => $paymentMethod ? $paymentMethod->id : null,
            'paymentClassification' => $paymentClassification,
            'planItemID' => isset($input['planItemID']) ? $input['planItemID'] : null,
            'productID' => $product ? $product->id : null,
            // Planogram snapshot. productMappingID comes from the vend (not the
            // resolved item) so it is captured even when the channel is unmapped
            // or the code is 0 — "which planogram was live" is knowable in every
            // case. productMappingItemID pins the exact channel_code -> product
            // -> selling_price row and is null when nothing resolved.
            'productMappingID' => $vend->product_mapping_id ?: null,
            'productMappingItemID' => $productMappingItem?->id,
            'qty' => isset($input['qty']) ? $input['qty'] : 1,
            'success_qty' => isset($input['success_qty']) ? $input['success_qty'] : 0,
            'dispensed_qty' => isset($input['dispensed_qty']) ? $input['dispensed_qty'] : 0,
            'time' => isset($input['time']) ? $input['time'] : null,
            'unitCostID' => $unitCostId,
            'unitCostValue' => $unitCostValue,
            'unit_price_amount' => $unitPriceAmount,
            'vendChannelCode' => $vendChannelCode,
            'vendChannelError' => $vendChannelError,
            'vendChannelErrorID' => $vendChannelError ? $vendChannelError->id : null,
            'vendChannelID' => $vendChannel ? $vendChannel->id : 0,
            'vouchers' => isset($input['vouchers']) ? $input['vouchers'] : null,
            'hid_card_id' => isset($input['hid_card_id']) ? $input['hid_card_id'] : null,
        ];
    }

    private function processInput($vend, $input)
    {
        $data = [];

        $data['originalJson'] = $input;
        $data['amount'] = isset($input['Price']) ? (isset($input['transf_info']) ? ($input['Price'] * 100) : $input['Price']) : 0;
        $data['dcvendUserID'] = isset($input['dcvend_user_id']) ? $input['dcvend_user_id'] : null;
        $data['dcvendDiscountAmount'] = isset($input['dcvend_discount_amount']) ? $input['dcvend_discount_amount'] : null;
        // Process labels: Legacy 'label' + New 'campaign_label_pivot'
        $labels = [];

        // 1. Handle legacy 'label' (could be string, JSON string, or array)
        if (isset($input['label'])) {
            $raw = $input['label'];
            if (is_array($raw)) {
                $labels = array_merge($labels, $raw);
            } elseif (is_string($raw)) {
                // Try decoding if it's a JSON string
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $labels = array_merge($labels, $decoded);
                } else {
                    // Otherwise treat as a single label string
                    $labels[] = $raw;
                }
            }
        }

        // 2. Handle 'campaign_label_pivot' -> resolve to "slug(id)"
        if (isset($input['campaign_label_pivot']) && is_array($input['campaign_label_pivot']) && ! empty($input['campaign_label_pivot'])) {
            $pivotIds = $input['campaign_label_pivot'];

            // Proposed Change: Look up Campaigns directly
            // The 'campaign_label_pivot' now contains Campaign IDs, not campaign_tag IDs.
            $campaigns = \App\Models\Campaign::whereIn('id', $pivotIds)->get();

            foreach ($campaigns as $campaign) {
                if ($campaign->slug) {
                    $labels[] = $campaign->slug.'('.$campaign->id.')';
                }
            }
        }

        // Deduplicate and assign
        $labels = array_values(array_unique($labels));
        $data['label'] = ! empty($labels) ? $labels : null;
        $data['orderID'] = isset($input['ORDRID']) ? $input['ORDRID'] : null;
        $data['paymentMethodCode'] = isset($input['PAY_TYPE']) ? $input['PAY_TYPE'] : null;
        $data['planItemID'] = isset($input['plan_item_id']) ? $input['plan_item_id'] : null;
        $data['time'] = isset($input['TIME']) ? $input['TIME'] : Carbon::now()->toDateTimeString();
        $data['errorCode'] = isset($input['SErr']) ? $input['SErr'] : (isset($input['errorCode']) ? $input['errorCode'] : 0);
        $data['vendChannelCode'] = isset($input['SId']) ? $input['SId'] : 0;
        $data['interfaceType'] = isset($input['TXN_SRC']) ? $input['TXN_SRC'] : null;
        $data['isMultiple'] = false;
        $data['children'] = [];
        $data['qty'] = 1;
        $data['vouchers'] = isset($input['vouchers']) ? $input['vouchers'] : null;
        $data['hid_card_id'] = isset($input['hid_card_id']) ? $input['hid_card_id'] : null;

        $successErrorCodes = [0, 6];
        $dispensedErrorCodes = [0, 6, 7, 9];
        $normalizedErrorCode = is_numeric($data['errorCode']) ? (int) $data['errorCode'] : null;

        $data['success_qty'] = in_array($normalizedErrorCode, $successErrorCodes, true) ? 1 : 0;
        $data['dispensed_qty'] = in_array($normalizedErrorCode, $dispensedErrorCodes, true) ? 1 : 0;

        if (isset($input['transf_info']) and count($input['transf_info']) == 1) {
            $data['qty'] = 1;
            $data['isMultiple'] = false;
            $data['errorCode'] = $input['transf_info'][0]['SErr'];
            $data['vendChannelCode'] = $input['transf_info'][0]['SId'];

            $singleErrorCode = is_numeric($input['transf_info'][0]['SErr']) ? (int) $input['transf_info'][0]['SErr'] : null;
            $data['success_qty'] = in_array($singleErrorCode, $successErrorCodes, true) ? 1 : 0;
            $data['dispensed_qty'] = in_array($singleErrorCode, $dispensedErrorCodes, true) ? 1 : 0;
        }

        if (isset($input['transf_info']) and count($input['transf_info']) > 1) {
            $data['isMultiple'] = true;
            $data['qty'] = count($input['transf_info']);
            $data['success_qty'] = 0;
            $data['dispensed_qty'] = 0;
            foreach ($input['transf_info'] as $trans) {
                $childAmount = $this->extractChildAmountCents($trans);
                $transErrorCode = is_numeric($trans['SErr']) ? (int) $trans['SErr'] : null;
                $childSuccessQty = in_array($transErrorCode, $successErrorCodes, true) ? 1 : 0;
                $childDispensedQty = in_array($transErrorCode, $dispensedErrorCodes, true) ? 1 : 0;
                $data['children'][] = $this->processMapping($vend, [
                    'amount' => $childAmount,
                    'errorCode' => $trans['SErr'],
                    'vendChannelCode' => $trans['SId'],
                    'success_qty' => $childSuccessQty,
                    'dispensed_qty' => $childDispensedQty,
                ]);
                $data['success_qty'] += $childSuccessQty;
                $data['dispensed_qty'] += $childDispensedQty;
            }
        }

        $transactionItemsCount = count($data['children']);
        if ($transactionItemsCount === 0) {
            $transactionItemsCount = max((int) ($data['qty'] ?? 0), 0);
        }
        $data['success_qty'] = min($data['success_qty'], $transactionItemsCount);
        $data['dispensed_qty'] = min($data['dispensed_qty'], $transactionItemsCount);

        return $data;
    }

    public function setDcvendParam($vendTransactionID)
    {
        $vendTransaction = VendTransaction::with([
            'customer:id,name',
            'paymentMethod:id,name',
            'vend:id,code,vend_prefix_id',
            'vend.vendPrefix:id,name',
            'vendTransactionItems.product:id,name',
            'vendTransactionItems.product.thumbnail',
            'vendTransactionItems.vendChannelError:id,code',
        ])
            ->find($vendTransactionID);

        $apkVerJson = (array) $vendTransaction->apk_ver_json;
        $parameterJson = (array) $vendTransaction->parameter_json;
        $vendTransactionJson = (array) $vendTransaction->vend_transaction_json;
        $metaJson = (array) $vendTransaction->meta_json;

        $data = [
            'id' => $vendTransaction->id,
            'apk_ver' => isset($apkVerJson['apkver']) ? $apkVerJson['apkver'] : null,
            'datetime' => $vendTransaction->created_at,
            'firmware_ver' => isset($parameterJson['Ver']) ? dechex($parameterJson['Ver']) : null,
            'total_amount' => $vendTransaction->amount,
            'customer_id' => $vendTransaction->customer_id,
            'customer_name' => $vendTransaction->customer?->name,
            'payment_method_id' => $vendTransaction->payment_method_id,
            'payment_method_name' => $vendTransaction->paymentMethod?->name,
            'plan_item_id' => isset($vendTransactionJson['plan_item_id']) ? $vendTransactionJson['plan_item_id'] : null,
            'ref_order_id' => $vendTransaction->order_id,
            'total_promo_amount' => isset($vendTransactionJson['dcvend_discount_amount']) ? $vendTransactionJson['dcvend_discount_amount'] : 0,
            'total_qty' => $vendTransaction->vendTransactionItems ? $vendTransaction->vendTransactionItems->count() : 1,
            'user_id' => isset($vendTransactionJson['dcvend_user_id']) ? $vendTransactionJson['dcvend_user_id'] : null,
            'vend_code' => $vendTransaction->vend?->code,
            'vend_id' => $vendTransaction->vend_id,
            'vend_prefix_id' => $vendTransaction->vend?->vend_prefix_id,
            'vend_prefix_name' => $vendTransaction->vend?->vendPrefix?->name,
            'vouchers' => isset($metaJson['vouchers']) ? $metaJson['vouchers'] : null,
        ];

        if ($vendTransaction->vendTransactionItems && $vendTransaction->vendTransactionItems->count() > 0) {
            $data['items'] = $vendTransaction->vendTransactionItems->map(function ($item) {
                return [
                    'product_id' => $item->product?->id,
                    'product_name' => $item->product?->name,
                    'product_thumbnail_url' => $item->product?->thumbnail?->full_url,
                    'qty' => $item->qty ?? 1,
                    'vend_channel_code' => $item->vend_channel_code,
                    'vend_channel_id' => $item->vend_channel_id,
                    'vend_channel_error_code' => $item->vend_channel_error_code,
                    'vend_channel_error_name' => $item->vendChannelError?->desc,
                    'vend_channel_error_id' => $item->vend_channel_error_id,
                ];
            });
        }

        if (empty($data['items']) && isset($vendTransactionJson['transf_info']) && count((array) $vendTransactionJson['transf_info']) > 0) {
            foreach ($vendTransactionJson['transf_info'] as $transfInfo) {

                $product = Product::find($transfInfo['goods_id']);
                $vendChannel = VendChannel::where('code', $transfInfo['SId'])->where('vend_id', $vendTransaction->vend_id)->first();
                $vendChannelError = VendChannelError::where('code', $transfInfo['SErr'])->first();
                $data['items'][] = [
                    'product_id' => $transfInfo['goods_id'],
                    'product_name' => $transfInfo['goods_name'],
                    'product_thumbnail_url' => $product?->thumbnail?->full_url,
                    'qty' => 1,
                    'vend_channel_code' => $transfInfo['SId'],
                    'vend_channel_id' => $vendChannel->id,
                    'vend_channel_error_code' => $transfInfo['SErr'],
                    'vend_channel_error_name' => $vendChannelError->desc,
                    'vend_channel_error_id' => $vendChannelError->id,
                ];
            }
        }

        return $data;
    }

    public function syncAllTotalsJson()
    {
        $vends = Vend::with('customer')->has('customer')->where('is_active', true)->get();

        foreach ($vends as $vend) {
            SyncVendTransactionTotalsJson::dispatch($vend)->onQueue('default');
        }
    }
}
