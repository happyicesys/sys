<?php

namespace App\Services\CardSettlement\Parsers;

use App\Contracts\CardSettlement\SettlementReportParser;
use App\Services\CardSettlement\ParsedReport;
use App\Services\CardSettlement\ParsedRow;
use App\Services\CardSettlement\SettlementParseException;
use Carbon\Carbon;

/**
 * NETS MerchantConnect "Standard Daily Report" CSV
 * (MCONNECT_<merchant>_STDRPT01_<yyyymmdd>_NEW.csv).
 *
 * Layout: a preamble (Merchant Account ID / Create Date / Cutover Date /
 * Total records counts), then a header row starting with "Product", then one
 * line per terminal event. Times are LOCAL (not UTC). One file spans two
 * calendar dates (the NETS business day cuts over ~22:30), so each row's own
 * Transaction Date is authoritative — never the filename or cutover date.
 *
 * Two dialects exist in the wild:
 *  - the raw portal download: dates "2026-08-30", times "22:30:58.000";
 *  - files round-tripped through Excel: dates "29/8/2026", times "48:59.0" —
 *    THE HOUR IS LOST (Excel reformats the time cell as mm:ss.0). Such rows
 *    are flagged time_is_partial and matched circularly within the hour.
 */
class NetsMerchantConnectParser implements SettlementReportParser
{
    public function provider(): string
    {
        return 'nets';
    }

    public function parse(string $path): ParsedReport
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw new SettlementParseException("Cannot open file: {$path}");
        }

        try {
            return $this->parseHandle($handle);
        } finally {
            fclose($handle);
        }
    }

    /** @param  resource  $handle */
    protected function parseHandle($handle): ParsedReport
    {
        $merchantAccount = null;
        $cutoverDate = null;
        $createDate = null;
        $createTime = null;
        $columns = null;
        $rows = [];
        $rowNo = 0;

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '' || trim($line, ", \t") === '') {
                continue;
            }
            // Strip a UTF-8 BOM off the very first line.
            if (str_starts_with($line, "\u{FEFF}")) {
                $line = substr($line, 3);
            }

            $cells = array_map('trim', str_getcsv($line, ',', '"', '\\'));

            if ($columns === null) {
                $label = $cells[0] ?? '';
                if (str_starts_with($label, 'Merchant Account ID')) {
                    $merchantAccount = $cells[1] ?? null;
                } elseif (str_starts_with($label, 'Cutover Date')) {
                    $cutoverDate = $this->parseCompactDate($cells[1] ?? '');
                } elseif (str_starts_with($label, 'Create Date')) {
                    $createDate = $this->parseCompactDate($cells[1] ?? '');
                } elseif (str_starts_with($label, 'Create Time')) {
                    $createTime = $cells[1] ?? null;
                } elseif ($label === 'Product' && in_array('Terminal ID', $cells, true)) {
                    $columns = array_flip($cells); // header name → index
                }

                continue;
            }

            $rowNo++;
            $rows[] = $this->parseDataRow($cells, $columns, $rowNo);
        }

        if ($columns === null) {
            throw new SettlementParseException(
                'Not a NETS MerchantConnect report: header row ("Product", …, "Terminal ID") not found.'
            );
        }

        return new ParsedReport(
            merchantAccount: $merchantAccount,
            cutoverDate: $cutoverDate,
            reportGeneratedAt: ($createDate && $createTime) ? "{$createDate} {$createTime}" : null,
            rows: $rows,
        );
    }

    protected function parseDataRow(array $cells, array $columns, int $rowNo): ParsedRow
    {
        $get = fn (string $name) => trim($cells[$columns[$name] ?? -1] ?? '');

        $terminalId = $get('Terminal ID');
        $dateRaw = $get('Transaction Date');
        if ($terminalId === '' || $dateRaw === '') {
            throw new SettlementParseException("Line {$rowNo}: missing Terminal ID or Transaction Date.");
        }

        [$time, $partial] = $this->parseTime($get('Transaction Time'));

        $amountRaw = $get('Transaction Amount (S$)');
        $amountCents = (int) round(((float) $amountRaw) * 100);

        $seq = $get('Txn Sequence Number');

        return new ParsedRow(
            rowNo: $rowNo,
            txnType: $get('Transaction Type'),
            product: $get('Product') ?: null,
            cardIssuer: $get('Financial Institution ID') ?: null,
            terminalId: $terminalId,
            transactionDate: $this->parseDate($dateRaw, $rowNo),
            transactionTime: $time,
            timeIsPartial: $partial,
            amountCents: $amountCents,
            sequenceNo: $seq === '' ? null : $seq,
        );
    }

    /** "2026-08-30" (raw) or "29/8/2026" (Excel round-trip) → Y-m-d */
    protected function parseDate(string $raw, int $rowNo): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        throw new SettlementParseException("Line {$rowNo}: unrecognised Transaction Date \"{$raw}\".");
    }

    /**
     * Returns [time, isPartial].
     *  "22:30:58.000" / "22:30:58"  → ["22:30:58", false]
     *  "48:59.0" / "48:59"          → ["00:48:59", true]  (hour lost to Excel;
     *                                  stored with a 00 hour so a TIME column
     *                                  holds it sanely — the flag says the
     *                                  hour is unknown, not midnight)
     *  ""                           → [null, false]
     */
    protected function parseTime(string $raw): array
    {
        if ($raw === '') {
            return [null, false];
        }
        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})(?:\.\d+)?$/', $raw, $m)) {
            return [sprintf('%02d:%02d:%02d', $m[1], $m[2], $m[3]), false];
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(?:\.\d+)?$/', $raw, $m)) {
            return [sprintf('00:%02d:%02d', $m[1], $m[2]), true];
        }

        return [null, false];
    }

    /** "20260829" → "2026-08-29" */
    protected function parseCompactDate(string $raw): ?string
    {
        if (preg_match('/^\d{8}$/', $raw)) {
            return Carbon::createFromFormat('Ymd', $raw)->format('Y-m-d');
        }

        return null;
    }
}
