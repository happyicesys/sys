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
     * LEGACY (2026-08-23 → 2026-09-02, writer removed 2026-09-08): a TRADE-time
     * inference that the NETS reader had reversed a failed single-item vend
     * (PAY_TYPE=1, single, err ∉ {0,6}, ISOK=0). Against the settlement
     * report it was right 46 times in 322. Nothing writes it any more; the
     * reconciler relabels a confirmed one to SETTLEMENT_REPORT_REVERSAL and
     * clears the rest. Kept only so historical rows still label.
     */
    public const CARD_TERMINAL_REVERSAL = 'card_terminal_reversal';

    /**
     * The acquirer's settlement report carried a reversal line for this sale
     * (NETS "Reversal Code = Y", negative amount) — the terminal DID return
     * the money. Written by CardSettlementRefundReconciler when a report is
     * synced. Since 2026-09-08 this is the ONLY writer of is_refunded on a
     * card-terminal sale: no TRADE footprint or machine signal sets it.
     */
    public const SETTLEMENT_REPORT_REVERSAL = 'settlement_report_reversal';

    /**
     * "NA in NETS" (Brian, 2026-09-09): a FAILED single-item card sale on a
     * terminal flagged `is_will_auto_refund` has NO line in either NETS file
     * that could carry it, once the day is final. The terminal voided the
     * approval before batch upload — the only way a Visa/MasterCard failure is
     * ever made good (scheme cards never get a reversal line). Written by
     * CardSettlementRefundReconciler; never for a dispensed sale, a multiple,
     * an unflagged terminal, or a terminal the report does not fully cover.
     */
    public const SETTLEMENT_REPORT_NOT_CAPTURED = 'settlement_report_not_captured';

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

    /** Our own server decided and called the refund, with no human involved. */
    public const TRIGGER_SERVER = 'server';

    /** A person did it: staff by hand, or outside ConnectVend entirely (gateway dashboard / dispute). */
    public const TRIGGER_USER = 'user';

    /**
     * WHO fired a gateway refund — the question that separates the Omise
     * sources from each other (Brian, 2026-09-09). Null for the card-terminal
     * sources: there the useful distinction is what the settlement report
     * SHOWED (a reversal, or a sale it never captured), which those surfaces
     * badge separately, and "server" would credit us with money the terminal
     * returned by itself.
     */
    public static function trigger(?string $source): ?string
    {
        return match ($source) {
            self::OMISE_NO_DISPENSE, self::OMISE_STALE_APPROVE, self::OMISE_TRADE_FAIL => self::TRIGGER_SERVER,
            self::OMISE_MANUAL, self::OMISE_EXTERNAL, self::MIDTRANS_EXTERNAL => self::TRIGGER_USER,
            default => null,
        };
    }

    /** Human-readable labels for badges / tooltips / exports. */
    public const LABELS = [
        self::OMISE_NO_DISPENSE => 'Omise — no dispense ACK within 10 min',
        self::OMISE_STALE_APPROVE => 'Omise — paid after the QR expired',
        self::OMISE_TRADE_FAIL => 'Omise — machine reported a dispense failure',
        self::OMISE_MANUAL => 'Omise — manual refund (artisan)',
        self::OMISE_EXTERNAL => 'Omise — refunded outside ConnectVend (dashboard / dispute / chargeback)',
        self::MIDTRANS_EXTERNAL => 'Midtrans — refunded at the gateway (webhook)',
        self::CARD_TERMINAL_REVERSAL => 'Card terminal reversal — inferred at TRADE time (legacy, unconfirmed)',
        self::SETTLEMENT_REPORT_REVERSAL => 'Card terminal reversal — confirmed by settlement report',
        self::SETTLEMENT_REPORT_NOT_CAPTURED => 'NA in NETS — failed card sale never captured (terminal voided before batch)',
        self::RETAINED_CREDIT_REVEND => 'Settled by re-vend from retained credit (no reversal)',
    ];

    public static function label(?string $source): ?string
    {
        return $source ? (self::LABELS[$source] ?? $source) : null;
    }
}
