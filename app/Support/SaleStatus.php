<?php

namespace App\Support;

use App\Models\VendTransaction;

/**
 * A sale has TWO outcomes, and the machine only reports one of them.
 *
 * The TRADE frame carries the dispense verdict per channel (SErr, one per
 * transf_info line) and nothing about money: ISOK is the VMC's own trade flag
 * on keypad frames (TXN_SRC 0) and hard-coded 1 on APK-built frames, and there
 * is no "payment collected" field. `vend_transactions.is_payment_received` is
 * NOT a payment signal either — VendTransactionService::processMapping() sets
 * it from the error code (0/6 → true) or forces it true for QR gateways, so on
 * cash and card sales it is the dispense result under a payment name.
 *
 * So the two labels are DEDUCED, each from the party that actually knows:
 *
 *   Payment  — only what a payment rail has CONFIRMED (Brian, 2026-09-02):
 *              a gateway sale (Omise / Midtrans / Fiuu) is Paid because the
 *              gateway's API created the row, and Refunded when the API/webhook
 *              or refund:sync-omise returned the money; a NETS card sale is
 *              Settled once the uploaded acquirer report matched it and
 *              Refunded when that report carried its reversal line. Anything
 *              not reconciled by a rail — cash, a card sale whose report is
 *              not uploaded yet — is left BLANK rather than guessed.
 *              Retained-credit rows (the VMC firmware fault, APK v303 frames)
 *              get their own words: the sale that consumed banked credit is
 *              "Retained credit" (no fresh authorisation, no money moved on
 *              this row), and the earlier failed sale it made whole is
 *              "Re-vended" — goods, not money, went back.
 *   Dispense — the machine's verdict: SErr ∈ {0,6} is a clean drop, anything
 *              else a failed motor/sensor. A single sale carries it on its own
 *              row; a multiple purchase carries it on EACH ITEM row and the
 *              parent row stays blank. A gateway row still waiting for its
 *              TRADE is Pending; a settled one the machine never reported is
 *              No report.
 *
 * One rule for the grid, the CSV exports and the refund screen — feed every
 * consumer through SaleFacts::fromRow() so the three never disagree again.
 */
final class SaleStatus
{
    // ── Payment ────────────────────────────────────────────────────────────

    public const PAID = 'Paid';

    public const SETTLED = 'Settled';

    public const REFUNDED = 'Refunded';

    /** The failed sale whose retained credit a later vend consumed: made whole by goods, not money. */
    public const RE_VENDED = 'Re-vended';

    /** Approved from credit the VMC/reader banked earlier — no fresh authorisation, no money moved on this row. */
    public const RETAINED_CREDIT = 'Retained credit';

    /** No payment rail has confirmed this row (cash; card terminal before its report is synced). Rendered blank. */
    public const UNCONFIRMED = '';

    // ── Dispense ───────────────────────────────────────────────────────────

    public const DISPENSED = 'Dispensed';

    public const FAILED = 'Failed';

    /** Gateway row paid, dispense outcome not decided yet (SETTLEMENT_PENDING). */
    public const PENDING = 'Pending';

    /** Gateway row paid and settled, but the machine never sent its TRADE. */
    public const NO_REPORT = 'No report';

    /** Multiple purchase: the verdict lives on the item rows, the parent row is blank. */
    public const ON_ITEMS = '';

    /**
     * Channel error codes the machine reports for a clean drop. Same set the
     * sales aggregates use (VendTransaction::salesItemTotalsSelect and friends).
     */
    public const DISPENSED_CODES = [0, 6];

    /** Money verdict — see the class docblock for which rail confirms what. */
    public static function payment(SaleFacts $sale): string
    {
        if ($sale->isRefunded) {
            return $sale->autoRefundSource === AutoRefundSource::RETAINED_CREDIT_REVEND
                ? self::RE_VENDED
                : self::REFUNDED;
        }

        if ($sale->settlementStatus === VendTransaction::SETTLEMENT_REFUNDED) {
            return self::REFUNDED;
        }

        if ($sale->isRetainedCreditSettlement) {
            return self::RETAINED_CREDIT;
        }

        if ($sale->confirmedBySettlementReport) {
            return self::SETTLED;
        }

        // A gateway row exists only once the gateway's paid callback arrived —
        // that includes SETTLEMENT_PENDING rows still waiting for their TRADE.
        if ($sale->paidThroughGateway) {
            return self::PAID;
        }

        return self::UNCONFIRMED;
    }

    /** Explains a retained-credit label for a badge tooltip; null for ordinary rows. */
    public static function paymentNote(SaleFacts $sale): ?string
    {
        return match (self::payment($sale)) {
            self::RETAINED_CREDIT => 'Approved from credit the card reader retained after an earlier failed vend (CSHL_ARMED_MS < 5 s) — no fresh card authorisation, no settlement to match.',
            self::RE_VENDED => 'The reader kept this payment as credit and a later vend consumed it — the customer was made whole by goods, no money was returned.',
            default => null,
        };
    }

    /**
     * Header-row dispense verdict. Single sale: the machine's verdict for its
     * channel. Multiple purchase: blank — each item row carries its own
     * (itemDispense). Gateway rows without a TRADE: Pending / No report.
     */
    public static function dispense(SaleFacts $sale): string
    {
        if ($sale->settlementStatus === VendTransaction::SETTLEMENT_PENDING) {
            return self::PENDING;
        }

        if (! $sale->isFoundInTransaction) {
            return self::NO_REPORT;
        }

        if ($sale->isMultiple) {
            return self::ON_ITEMS;
        }

        return self::itemDispense($sale->headerErrorCode);
    }

    /**
     * Did this channel drop its product? A missing code means the machine
     * reported no fault (legacy rows and single vends store no code at all).
     */
    public static function itemDispensed(int|string|null $errorCode): bool
    {
        if ($errorCode === null || $errorCode === '') {
            return true;
        }

        return is_numeric($errorCode) && in_array((int) $errorCode, self::DISPENSED_CODES, true);
    }

    /** One item row's verdict (a multiple's line, or a single sale's channel). */
    public static function itemDispense(int|string|null $errorCode): string
    {
        return self::itemDispensed($errorCode) ? self::DISPENSED : self::FAILED;
    }
}
