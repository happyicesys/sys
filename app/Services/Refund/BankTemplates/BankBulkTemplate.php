<?php

namespace App\Services\Refund\BankTemplates;

use Illuminate\Support\Collection;

/**
 * Contract for a bank bulk-transfer file generator. Add a new bank by creating
 * a class that implements this and registering it in BankTemplateRegistry.
 */
interface BankBulkTemplate
{
    /** Machine key, e.g. "cimb". */
    public function key(): string;

    /** Human label for the UI dropdown. */
    public function label(): string;

    /** File extension for the generated file, e.g. "txt" or "csv". */
    public function fileExtension(): string;

    /**
     * Build the file content from the given approved refund tickets.
     *
     * @param Collection $tickets  RefundTicket models
     * @param array $meta          ['batch' => RefundPayoutBatch, ...]
     */
    public function generate(Collection $tickets, array $meta = []): string;
}
