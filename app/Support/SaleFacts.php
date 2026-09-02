<?php

namespace App\Support;

/**
 * The facts about one sale that decide its Payment and Dispense labels —
 * read once from a vend_transactions row (Eloquent model, or a joined grid /
 * export row) so every consumer feeds SaleStatus the same inputs.
 *
 * Columns read: is_multiple, vend_channel_error_code (or the loaded
 * vendChannelError.code), settlement_status, is_found_in_transaction,
 * is_refunded, auto_refund_source, is_retained_credit_settlement,
 * card_settlement_synced_at, and the payment rail via the grid/export alias
 * payment_method_gateway_id or the loaded paymentMethod.payment_gateway_id.
 */
final readonly class SaleFacts
{
    public function __construct(
        public bool $isMultiple,
        public int|string|null $headerErrorCode,
        public ?int $settlementStatus,
        public bool $isFoundInTransaction,
        public bool $isRefunded,
        public ?string $autoRefundSource,
        public bool $isRetainedCreditSettlement,
        /** Paid through a payment gateway (Omise / Midtrans / Fiuu): the row exists because the gateway's paid callback said so. */
        public bool $paidThroughGateway,
        /** Card-terminal sale confirmed by an uploaded acquirer settlement report (card_settlement_synced_at). */
        public bool $confirmedBySettlementReport,
    ) {}

    public static function fromRow(object $row): self
    {
        $loaded = fn (string $relation): bool => method_exists($row, 'relationLoaded') && $row->relationLoaded($relation);

        $headerErrorCode = $row->vend_channel_error_code
            ?? ($loaded('vendChannelError') ? $row->vendChannelError?->code : null);

        $gatewayId = $row->payment_method_gateway_id
            ?? ($loaded('paymentMethod') ? $row->paymentMethod?->payment_gateway_id : null);

        return new self(
            isMultiple: (bool) ($row->is_multiple ?? false),
            headerErrorCode: $headerErrorCode,
            settlementStatus: isset($row->settlement_status) ? (int) $row->settlement_status : null,
            isFoundInTransaction: (bool) ($row->is_found_in_transaction ?? true),
            isRefunded: (bool) ($row->is_refunded ?? false),
            autoRefundSource: $row->auto_refund_source ?? null,
            isRetainedCreditSettlement: (bool) ($row->is_retained_credit_settlement ?? false),
            paidThroughGateway: $gatewayId !== null,
            confirmedBySettlementReport: ! empty($row->card_settlement_synced_at),
        );
    }
}
