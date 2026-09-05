<?php

namespace App\Support;

/**
 * Values for vend_transactions.auto_refund_source — WHICH mechanism returned the
 * customer's money when is_refunded was set by the system (never by a manual
 * PayNow/PayPal ticket payout; those live on refund_tickets).
 *
 * Integrity rule (REFUND_INTEGRITY_AUDIT_2026-08-23.md): is_refunded = true is
 * written only AFTER the money has actually been returned — by the Omise API
 * accepting the refund, or by the card terminal reversing the charge — and
 * always together with one of these sources.
 */
final class AutoRefundSource
{
    /** Omise: paid but the machine never ACKed the order within 10 min (scanner). */
    public const OMISE_NO_DISPENSE = 'omise_no_dispense';

    /** Omise: webhook approved the charge > 210 s after the QR was created. */
    public const OMISE_STALE_APPROVE = 'omise_stale_approve';

    /** Omise: the machine's TRADE reported a single-item dispense failure. */
    public const OMISE_TRADE_FAIL = 'omise_trade_fail';

    /** Omise: `php artisan refund:omise {orderId}` run by an operator. */
    public const OMISE_MANUAL = 'omise_manual';

    /**
     * Omise refunded the charge WITHOUT mark1 calling the API: a refund made on
     * the Omise dashboard, or a dispute/chargeback Omise accepted as a refund.
     * Learned from the `refund.create` webhook or `refund:sync-omise`.
     */
    public const OMISE_EXTERNAL = 'omise_external';

    /**
     * Midtrans refunded the charge (refund / partial_refund webhook) — the
     * gateway's own record, mark1 never called a refund API for Midtrans.
     */
    public const MIDTRANS_EXTERNAL = 'midtrans_external';

    /**
     * Card terminal (NETS family) reversed the charge at the machine after a
     * single-item dispense failure — recorded from the TRADE footprint
     * (PAY_TYPE=1, single, err ∉ {0,6}, ISOK=0), not from a processor callback.
     */
    public const CARD_TERMINAL_REVERSAL = 'card_terminal_reversal';

    /**
     * The acquirer's settlement report carried a reversal line for this sale
     * (NETS "Reversal Code = Y", negative amount) — the terminal DID return
     * the money. Written by CardSettlementSyncService when the user syncs a
     * matched report. Since 2026-09-02 this replaces the TRADE-time
     * card_terminal_reversal inference for NETS (config
     * refund.card_reversal_terminals is empty).
     */
    public const SETTLEMENT_REPORT_REVERSAL = 'settlement_report_reversal';

    /**
     * The ONE deliberate exception to "money has been returned": the customer
     * was made whole by GOODS, not money. A later card trade with
     * CSHL_ARMED_MS < 5000 proved the reader did NOT reverse this sale's
     * charge — it retained the credit and vended against it
     * (RetainedCreditSettlementRecorder rewrites a falsified
     * card_terminal_reversal to this). is_refunded stays true because its
     * operational meaning — "do not compensate this customer again" — still
     * holds: their payment bought the re-vend.
     */
    public const RETAINED_CREDIT_REVEND = 'retained_credit_revend';

    /** Human-readable labels for badges / tooltips / exports. */
    public const LABELS = [
        self::OMISE_NO_DISPENSE => 'Omise — no dispense ACK within 10 min',
        self::OMISE_STALE_APPROVE => 'Omise — paid after the QR expired',
        self::OMISE_TRADE_FAIL => 'Omise — machine reported a dispense failure',
        self::OMISE_MANUAL => 'Omise — manual refund (artisan)',
        self::OMISE_EXTERNAL => 'Omise — refunded outside ConnectVend (dashboard / dispute / chargeback)',
        self::MIDTRANS_EXTERNAL => 'Midtrans — refunded at the gateway (webhook)',
        self::CARD_TERMINAL_REVERSAL => 'Card terminal reversal (NETS)',
        self::SETTLEMENT_REPORT_REVERSAL => 'Card terminal reversal — confirmed by settlement report',
        self::RETAINED_CREDIT_REVEND => 'Settled by re-vend from retained credit (no reversal)',
    ];

    public static function label(?string $source): ?string
    {
        return $source ? (self::LABELS[$source] ?? $source) : null;
    }
}
