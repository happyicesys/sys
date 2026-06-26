<?php

namespace App\Services\Refund;

use App\Models\RefundTicket;

/**
 * Runs the auto-validation pass over the items a customer flagged and produces
 * an admin recommendation + evidence, using data already captured at purchase:
 *   - vend_transaction_items.vend_channel_error_id / weightage  (did it dispense?)
 *   - is_refunded on the item / transaction                     (double-refund guard)
 */
class RefundValidationService
{
    /**
     * @param array<int, array> $items  normalized items (see RefundTicketService::normalize*)
     * @param array $context  ['is_auto_refund_channel'=>bool, 'txn_already_refunded'=>bool, 'is_manual'=>bool]
     * @return array{items: array, recommendation: string, auto_refund_detected: bool, evidence: array}
     */
    public function validate(array $items, array $context = []): array
    {
        $isAutoChannel = (bool) ($context['is_auto_refund_channel'] ?? false);
        $txnRefunded = (bool) ($context['txn_already_refunded'] ?? false);
        $isManual = (bool) ($context['is_manual'] ?? false);

        $validated = [];
        $verdicts = [];
        $anyAlreadyRefunded = false;

        foreach ($items as $item) {
            $alreadyRefunded = $txnRefunded || (bool) ($item['is_refunded'] ?? false);
            $hadError = (bool) ($item['had_channel_error'] ?? false);

            if ($alreadyRefunded) {
                $verdict = RefundTicket::REC_REJECT;          // already refunded -> never pay twice
                $anyAlreadyRefunded = true;
            } elseif ($hadError) {
                $verdict = RefundTicket::REC_PROCEED;          // channel error logged -> genuine non-dispense
            } else {
                $verdict = RefundTicket::REC_REVIEW;           // no error -> needs human eyes
            }

            $item['item_recommendation'] = $verdict;
            $item['already_refunded'] = $alreadyRefunded;
            $validated[] = $item;
            $verdicts[] = $verdict;
        }

        $recommendation = $this->rollup($verdicts, $isManual);

        $evidence = [
            'item_count' => count($validated),
            'verdicts' => array_count_values($verdicts),
            'is_auto_refund_channel' => $isAutoChannel,
            'txn_already_refunded' => $txnRefunded,
            'is_manual' => $isManual,
            'evaluated_at' => now()->toDateTimeString(),
        ];

        return [
            'items' => $validated,
            'recommendation' => $recommendation,
            'auto_refund_detected' => $anyAlreadyRefunded,
            'evidence' => $evidence,
        ];
    }

    protected function rollup(array $verdicts, bool $isManual): string
    {
        if (empty($verdicts)) {
            return RefundTicket::REC_REVIEW;
        }
        // every selected item already refunded -> reject the new payout
        if (count(array_filter($verdicts, fn ($v) => $v === RefundTicket::REC_REJECT)) === count($verdicts)) {
            return RefundTicket::REC_REJECT;
        }
        // manual tickets always need a human
        if ($isManual) {
            return RefundTicket::REC_REVIEW;
        }
        // any item needing review -> whole ticket needs review
        if (in_array(RefundTicket::REC_REVIEW, $verdicts, true)) {
            return RefundTicket::REC_REVIEW;
        }
        return RefundTicket::REC_PROCEED;
    }
}
