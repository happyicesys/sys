<?php

namespace App\Services\CardSettlement;

class ParsedRow
{
    public function __construct(
        public readonly int $rowNo,                 // 1-based data-line number in the file
        public readonly string $txnType,            // Purchase / Logon / …
        public readonly ?string $product,           // EFTPOS / Scheme Credit/Debit / …
        public readonly ?string $cardIssuer,        // Financial Institution ID column
        public readonly string $terminalId,
        public readonly string $transactionDate,    // Y-m-d
        public readonly ?string $transactionTime,   // H:i:s when full; 00:i:s when partial
        public readonly bool $timeIsPartial,        // hour lost (Excel-damaged file)
        public readonly int $amountCents,           // signed: a reversal line carries the negative amount
        public readonly ?string $sequenceNo,
        public readonly bool $isReversal = false,   // Reversal Code = Y (or Void Txn Indicator = Y)
    ) {}

    public function isPurchase(): bool
    {
        return strcasecmp($this->txnType, 'Purchase') === 0;
    }
}
