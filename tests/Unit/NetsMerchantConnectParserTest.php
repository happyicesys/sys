<?php

namespace Tests\Unit;

use App\Services\CardSettlement\Parsers\NetsMerchantConnectParser;
use App\Services\CardSettlement\SettlementParseException;
use PHPUnit\Framework\TestCase;

/**
 * The NETS MerchantConnect daily CSV exists in two dialects: the raw portal
 * download (ISO dates, full HH:MM:SS times) and Excel round-tripped copies
 * (d/m/Y dates, times collapsed to mm:ss.0 — THE HOUR IS LOST). The parser
 * must read both and flag the degraded rows so matching switches to the
 * circular within-the-hour strategy.
 */
class NetsMerchantConnectParserTest extends TestCase
{
    private function parseString(string $content)
    {
        $path = tempnam(sys_get_temp_dir(), 'nets-test-');
        file_put_contents($path, $content);
        try {
            return (new NetsMerchantConnectParser)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    private function header(): string
    {
        return implode("\n", [
            'MerchantConnect Standard Daily Report,,,,,,,,,,,,,,,,,,,,,,,,',
            '"EFTPOS, Cross Border and Cashcard",,,,,,,,,,,,,,,,,,,,,,,,',
            'Merchant Account ID,H06228,,,,,,,,,,,,,,,,,,,,,,,',
            'Create Date (YYYYMMDD),20260830,,,,,,,,,,,,,,,,,,,,,,,',
            'Create Time (HH:MM:SS),13:22:57,,,,,,,,,,,,,,,,,,,,,,,',
            'Cutover Date (YYYYMMDD),20260829,,,,,,,,,,,,,,,,,,,,,,,',
            'Total records counts,3,,,,,,,,,,,,,,,,,,,,,,,',
            'Product,Transaction Type,Transaction Date,Transaction Time,Financial Institution ID,Corporation ID,Retailer ID,Merchant ID,Terminal ID,Transaction Amount (S$),Cashback Amount (S$),Merchant Fees,Purchase Fees,Business Date,Business Time,Card Issuer ID,Reversal Code,CashCard Application Number (CAN),Txn Sequence Number,Txn Reference Number,Void Txn Original TID,Void Txn Original Date,Void Txn Original Time,Original Sequence No,Void Txn Indicator',
        ]);
    }

    public function test_parses_raw_portal_format()
    {
        $report = $this->parseString($this->header()."\n"
            .'EFTPOS,Purchase,2026-08-29,22:30:58.000,DBS Card,,11101048429,,23082824,2.4,0,0,0,,,,N,,2150,,,,,,N'."\n"
            .'Scheme Credit/Debit,Purchase,2026-08-30,01:15:25.000,VISA,,11101048429,1.11E+11,23082824,4.6,0,0,0,,,,N,4628xxxxxxxx0962,95938,NA,,,,,N'."\n"
            .'EFTPOS,Logon,2026-08-30,08:45:33.000,,,11101048429,,23082824,0,0,0,0,,,,N,,0,,,,,,N');

        $this->assertSame('H06228', $report->merchantAccount);
        $this->assertSame('2026-08-29', $report->cutoverDate);
        $this->assertSame('2026-08-30 13:22:57', $report->reportGeneratedAt);
        $this->assertCount(3, $report->rows);

        $row = $report->rows[0];
        $this->assertSame('Purchase', $row->txnType);
        $this->assertSame('23082824', $row->terminalId);
        $this->assertSame('2026-08-29', $row->transactionDate);
        $this->assertSame('22:30:58', $row->transactionTime);
        $this->assertFalse($row->timeIsPartial);
        $this->assertSame(240, $row->amountCents);
        $this->assertSame('2150', $row->sequenceNo);

        $this->assertSame(460, $report->rows[1]->amountCents);
        $this->assertFalse($report->rows[2]->isPurchase());
    }

    public function test_parses_excel_damaged_format_and_flags_partial_time()
    {
        $report = $this->parseString($this->header()."\n"
            .'EFTPOS,Purchase,29/8/2026,48:59.0,DBS Card,,11101059116,,23100690,1.6,0,0,0,,,,N,,1208,,,,,,N');

        $row = $report->rows[0];
        $this->assertSame('2026-08-29', $row->transactionDate);
        $this->assertTrue($row->timeIsPartial);
        // Stored with a 00 hour so a TIME column keeps mm:ss; the flag says
        // the hour is unknown, not midnight.
        $this->assertSame('00:48:59', $row->transactionTime);
        $this->assertSame(160, $row->amountCents);
    }

    public function test_rejects_a_file_without_the_nets_header()
    {
        $this->expectException(SettlementParseException::class);
        $this->parseString("foo,bar\n1,2\n");
    }
}
