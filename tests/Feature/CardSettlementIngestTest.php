<?php

namespace Tests\Feature;

use App\Jobs\MatchCardSettlementReport;
use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MatchCardSettlementReport ingestion — parses the stored file into
 * card_settlement_rows once, and marks repeats DUPLICATE via the fingerprint
 * (same file re-uploaded, or overlapping cutover windows: the NETS business
 * day spans two calendar dates, so consecutive daily files share edge rows).
 */
class CardSettlementIngestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        PaymentMethod::create(['code' => 1, 'name' => 'Card Terminal', 'is_active' => true]);
    }

    private function csv(): string
    {
        return implode("\n", [
            'MerchantConnect Standard Daily Report,,,,',
            'Merchant Account ID,H06228,,,',
            'Create Date (YYYYMMDD),20260830,,,',
            'Create Time (HH:MM:SS),13:22:57,,,',
            'Cutover Date (YYYYMMDD),20260829,,,',
            'Total records counts,2,,,',
            'Product,Transaction Type,Transaction Date,Transaction Time,Financial Institution ID,Corporation ID,Retailer ID,Merchant ID,Terminal ID,Transaction Amount (S$),Cashback Amount (S$),Merchant Fees,Purchase Fees,Business Date,Business Time,Card Issuer ID,Reversal Code,CashCard Application Number (CAN),Txn Sequence Number,Txn Reference Number,Void Txn Original TID,Void Txn Original Date,Void Txn Original Time,Original Sequence No,Void Txn Indicator',
            'EFTPOS,Purchase,2026-08-29,22:30:58.000,DBS Card,,11101048429,,23082824,2.4,0,0,0,,,,N,,2150,,,,,,N',
            'EFTPOS,Logon,2026-08-30,08:45:33.000,,,11101048429,,23082824,0,0,0,0,,,,N,,0,,,,,,N',
        ]);
    }

    private function uploadedReport(): CardSettlementReport
    {
        $path = 'sys/card-settlements/'.uniqid().'.csv';
        Storage::put($path, $this->csv());

        $report = CardSettlementReport::create([
            'provider' => 'nets',
            'original_filename' => 'MCONNECT_test.csv',
            'status' => CardSettlementReport::STATUS_UPLOADED,
        ]);
        $report->attachments()->create([
            'full_url' => Storage::url($path),
            'local_url' => $path,
            'type' => 'card-settlement-report',
        ]);

        return $report;
    }

    public function test_ingests_rows_and_report_metadata()
    {
        $report = $this->uploadedReport();

        (new MatchCardSettlementReport($report->id))->handle(app(\App\Services\CardSettlement\CardSettlementMatcher::class));

        $report->refresh();
        $this->assertSame('H06228', $report->merchant_account);
        $this->assertSame('2026-08-29', $report->cutover_date->toDateString());
        $this->assertSame(2, $report->total_rows);
        $this->assertSame(1, $report->purchase_rows);
        $this->assertSame(CardSettlementReport::STATUS_REVIEW, $report->status);
        $this->assertSame(2, $report->rows()->count());
        // No binding exists → the purchase row surfaces as a query, not an error.
        $this->assertSame(1, $report->rows()->where('resolution_note', 'No terminal binding')->count());
    }

    public function test_reingesting_the_same_lines_marks_them_duplicate()
    {
        $first = $this->uploadedReport();
        (new MatchCardSettlementReport($first->id))->handle(app(\App\Services\CardSettlement\CardSettlementMatcher::class));

        $second = $this->uploadedReport();
        (new MatchCardSettlementReport($second->id))->handle(app(\App\Services\CardSettlement\CardSettlementMatcher::class));

        $this->assertSame(
            2,
            $second->rows()->where('status', CardSettlementRow::STATUS_DUPLICATE)->count()
        );
        $this->assertSame(2, $second->fresh()->duplicate_count);
    }
}
