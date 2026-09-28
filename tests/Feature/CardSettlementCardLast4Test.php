<?php

namespace Tests\Feature;

use App\Jobs\ExportVendTransactionCsv;
use App\Jobs\ExportVendTransactionCsvChunk;
use App\Jobs\MatchCardSettlementReport;
use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\ExportJob;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\VendTransaction;
use App\Services\CardSettlement\CardLast4Lookup;
use App\Services\CardSettlement\Parsers\NetsMerchantConnectParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The paying card's last 4 digits, from the NETS "CashCard Application Number
 * (CAN)" column (2026-09-28): parsed on ingest, backfilled from stored files,
 * and carried into the converted download and the Sales Transactions CSVs so
 * a retained-credit sale can be checked against the card of the failed sale
 * whose credit it used.
 */
class CardSettlementCardLast4Test extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'Product,Transaction Type,Transaction Date,Transaction Time,Financial Institution ID,Corporation ID,Retailer ID,Merchant ID,Terminal ID,Transaction Amount (S$),Cashback Amount (S$),Merchant Fees,Purchase Fees,Business Date,Business Time,Card Issuer ID,Reversal Code,CashCard Application Number (CAN),Txn Sequence Number,Txn Reference Number,Void Txn Original TID,Void Txn Original Date,Void Txn Original Time,Original Sequence No,Void Txn Indicator';

    private ?int $vendId = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(CardSettlementReport::storageDisk());
        PaymentMethod::firstOrCreate(['code' => PaymentMethod::CODE_CARD_TERMINAL], ['name' => 'Card Terminal', 'is_active' => true]);
    }

    /** Real shapes from the Aug–Sep 2026 files (91,748 lines). */
    private function csv(): string
    {
        return implode("\n", [
            'MerchantConnect Standard Daily Report,,,,',
            'Merchant Account ID,H06228,,,',
            'Cutover Date (YYYYMMDD),20260921,,,',
            self::HEADER,
            'Scheme Credit/Debit,Purchase,2026-09-21,22:01:57.000,MasterCard,,1.11E+10,1.11E+11,23082801,1.7,0,0,0,,,,N,5349xxxxxxxx9265,057681,NA,,,,,N',
            'Scheme Credit/Debit,Purchase,2026-09-21,22:06:39.000,MasterCard,,1.11E+10,1.11E+11,23082801,0.7,0,0,0,,,,N,535558xxxxxx2599,057683,NA,,,,,N',
            'EFTPOS,Purchase,2026-09-21,19:49:02.000,DBS Card,,1.11E+10,,23082801,1.7,0,0,0,,,,N,,1760,,,,,,N',
            'EFTPOS,Logon,2026-09-21,11:01:45.000,DBS Settlement,,1.11E+10,,23082801,0,0,0,0,,,NA,N,,1759,,,,,,N',
            'CROSS BORDER,Purchase,2026-09-21,12:00:00.000,VISA,,1.11E+10,,23082801,3,0,0,0,,,,N,462812*********9,057690,NA,,,,,N',
            'FLASHPAY,Purchase,2026-09-21,13:00:00.000,FlashPay,,1.11E+10,,23082801,2,0,0,0,,,,N,1111222233334444,057691,NA,,,,,N',
        ]);
    }

    private function storedReport(string $csv): CardSettlementReport
    {
        $disk = CardSettlementReport::storageDisk();
        $path = 'card-settlements/'.uniqid().'.csv';
        Storage::disk($disk)->put($path, $csv);
        $report = CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'MCONNECT_test.csv', 'storage_disk' => $disk, 'status' => CardSettlementReport::STATUS_UPLOADED]);
        $report->attachments()->create(['full_url' => '/x', 'local_url' => $path, 'type' => 'card-settlement-report']);

        return $report;
    }

    public function test_the_parser_keeps_only_four_real_trailing_digits(): void
    {
        $this->assertSame('1234', NetsMerchantConnectParser::cardLast4('4628xxxxxxxx1234'));
        $this->assertSame('1234', NetsMerchantConnectParser::cardLast4('462812******1234'));
        $this->assertSame('4444', NetsMerchantConnectParser::cardLast4('1111222233334444'));
        $this->assertSame('1234', NetsMerchantConnectParser::cardLast4(" '4628xxxxxxxx1234 "));
        // One digit shown: "***9" would pair unrelated cards.
        $this->assertNull(NetsMerchantConnectParser::cardLast4('462812*********9'));
        // Excel re-save: the digits are gone.
        $this->assertNull(NetsMerchantConnectParser::cardLast4('1.11E+15'));
        $this->assertNull(NetsMerchantConnectParser::cardLast4(''));
        $this->assertNull(NetsMerchantConnectParser::cardLast4('NA'));
    }

    public function test_ingest_stores_the_card_on_each_line(): void
    {
        Bus::fake();
        $report = $this->storedReport($this->csv());

        app()->call([new MatchCardSettlementReport($report->id), 'handle']);

        $cards = $report->rows()->orderBy('row_no')->pluck('card_last4', 'row_no')->all();
        $this->assertSame([1 => '9265', 2 => '2599', 3 => null, 4 => null, 5 => null, 6 => '4444'], $cards);
    }

    public function test_backfill_fills_only_empty_cells_and_only_after_apply(): void
    {
        $report = $this->storedReport($this->csv());
        foreach ([[1, 170, '2026-09-21'], [2, 70, '2026-09-21'], [3, 170, '2026-09-21']] as [$no, $amount, $date]) {
            $this->row($report, $no, $amount, $date);
        }

        $this->artisan('card-settlement:backfill-card-last4')->assertSuccessful();
        $this->assertSame(0, CardSettlementRow::whereNotNull('card_last4')->count(), 'dry run writes nothing');

        $this->artisan('card-settlement:backfill-card-last4', ['--apply' => true])->assertSuccessful();
        $this->assertSame([1 => '9265', 2 => '2599', 3 => null], $report->rows()->orderBy('row_no')->pluck('card_last4', 'row_no')->all());

        // Idempotent, and never overwrites a value already there.
        CardSettlementRow::where('row_no', 1)->update(['card_last4' => '0000']);
        $this->artisan('card-settlement:backfill-card-last4', ['--apply' => true])->assertSuccessful();
        $this->assertSame('0000', CardSettlementRow::where('row_no', 1)->value('card_last4'));
    }

    public function test_backfill_skips_a_report_whose_lines_do_not_line_up(): void
    {
        $report = $this->storedReport($this->csv());
        $this->row($report, 1, 170, '2026-09-21');
        $this->row($report, 2, 999, '2026-09-21'); // stored amount ≠ file line 2

        $this->artisan('card-settlement:backfill-card-last4', ['--apply' => true])->assertFailed();

        $this->assertSame(0, CardSettlementRow::whereNotNull('card_last4')->count(), 'a shifted row_no must not write one card');
    }

    public function test_lookup_gives_the_sale_card_and_the_card_whose_credit_it_used(): void
    {
        $failed = $this->sale(['amount' => 170]);
        $retry = $this->sale(['amount' => 240, 'is_retained_credit_settlement' => true, 'retained_credit_settles_txn_id' => $failed->id]);
        $cash = $this->sale(['amount' => 100]);
        $report = CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'x.csv', 'status' => CardSettlementReport::STATUS_SYNCED]);
        $this->row($report, 1, 170, '2026-09-21', ['matched_vend_transaction_id' => $failed->id, 'card_last4' => '9265']);
        $this->row($report, 2, 70, '2026-09-21', ['matched_vend_transaction_id' => $retry->id, 'card_last4' => '2599']);

        $cards = CardLast4Lookup::forSales(VendTransaction::withoutGlobalScopes()->whereIn('id', [$failed->id, $retry->id, $cash->id])->get());

        $this->assertSame(['card' => '9265', 'credit_from' => null], $cards[$failed->id]);
        $this->assertSame(['card' => '2599', 'credit_from' => '9265'], $cards[$retry->id]);
        $this->assertArrayNotHasKey($cash->id, $cards);
    }

    public function test_converted_download_carries_the_card(): void
    {
        $report = CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'x.csv', 'status' => CardSettlementReport::STATUS_SYNCED]);
        $this->row($report, 1, 170, '2026-09-21', ['card_last4' => '9265']);
        Permission::firstOrCreate(['name' => 'read card-settlements', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('read card-settlements');

        $csv = $this->actingAs($user)->get('/card-settlements/'.$report->id.'/download-converted')->assertOk()->streamedContent();

        $lines = array_map('str_getcsv', array_filter(explode("\n", trim(substr($csv, 3)))));
        $col = array_search('Card Last 4', $lines[0], true);
        $this->assertNotFalse($col);
        $this->assertSame('9265', $lines[1][$col]);
        $this->assertCount(count($lines[0]), $lines[1]);
    }

    #[DataProvider('exportJobs')]
    public function test_the_sales_csv_carries_both_cards_in_the_last_two_columns(string $which): void
    {
        Storage::fake('digitaloceanspaces');
        $operator = Operator::withoutGlobalScopes()->create(['code' => 'TSTOP', 'name' => 'Test Operator', 'is_active' => true]);
        $user = User::factory()->create(['operator_id' => $operator->id]);
        $failed = $this->sale(['amount' => 170, 'operator_id' => $operator->id]);
        $retry = $this->sale(['amount' => 240, 'operator_id' => $operator->id, 'is_retained_credit_settlement' => true, 'retained_credit_settles_txn_id' => $failed->id]);
        $report = CardSettlementReport::create(['provider' => 'nets', 'original_filename' => 'x.csv', 'status' => CardSettlementReport::STATUS_SYNCED]);
        $this->row($report, 1, 170, '2026-09-21', ['matched_vend_transaction_id' => $failed->id, 'card_last4' => '9265']);
        $this->row($report, 2, 70, '2026-09-21', ['matched_vend_transaction_id' => $retry->id, 'card_last4' => '2599']);
        $job = ExportJob::create(['user_id' => $user->id, 'type' => 'vend_transaction', 'status' => 'pending', 'filename' => 'x']);
        $request = ['date_from' => '2026-09-21', 'date_to' => '2026-09-21', 'operators' => [$operator->id]];

        if ($which === 'single') {
            (new ExportVendTransactionCsv($job->id, $request, $user->id, null, null))->handle();
        } else {
            \App\Models\ExportJobChunk::create(['export_job_id' => $job->id, 'chunk_index' => 0, 'status' => 'pending']);
            (new ExportVendTransactionCsvChunk($job->id, $request, $user->id, 0, 20000, $failed->id, $retry->id, null, null))->handle();
        }

        $this->assertNotSame('failed', $job->fresh()->status, (string) $job->fresh()->error_message);
        // The chunk job writes its CSV part, then zips the parts; read the part.
        $files = array_values(array_filter(Storage::disk('digitaloceanspaces')->allFiles(), fn ($f) => str_ends_with($f, '.csv')));
        $this->assertNotEmpty($files);
        $csv = Storage::disk('digitaloceanspaces')->get($files[0]);
        $lines = array_map('str_getcsv', array_values(array_filter(explode("\n", trim($csv)))));
        $header = $lines[0];
        $this->assertSame(['Card Last 4', 'Credit From Card'], array_slice($header, -2));
        $byAmount = collect(array_slice($lines, 1))->filter(fn ($l) => count($l) === count($header))->keyBy(fn ($l) => $l[array_search('Amount', $header, true)]);
        $this->assertSame(['9265', ''], array_slice($byAmount['1.7'], -2));
        $this->assertSame(['2599', '9265'], array_slice($byAmount['2.4'], -2));
        foreach (array_slice($lines, 1) as $l) {
            $this->assertCount(count($header), $l, 'every row as wide as the header');
        }
    }

    public static function exportJobs(): array
    {
        return ['single file' => ['single'], 'chunked' => ['chunk']];
    }

    private function row(CardSettlementReport $report, int $rowNo, int $amount, string $date, array $extra = []): CardSettlementRow
    {
        return CardSettlementRow::create($extra + [
            'card_settlement_report_id' => $report->id, 'row_no' => $rowNo, 'txn_type' => 'Purchase', 'terminal_id' => '23082801',
            'transaction_date' => $date, 'transaction_time' => '22:00:00', 'time_is_partial' => false, 'amount_cents' => $amount,
            'fingerprint' => sha1(uniqid('', true)), 'status' => isset($extra['matched_vend_transaction_id']) ? CardSettlementRow::STATUS_MATCHED : CardSettlementRow::STATUS_PENDING,
        ]);
    }

    private function sale(array $attrs): VendTransaction
    {
        $vendId = $this->vendId ??= DB::table('vends')->insertGetId(['code' => 5073, 'name' => 'Machine 5073', 'is_testing' => 0, 'operator_id' => $attrs['operator_id'] ?? null, 'created_at' => now(), 'updated_at' => now()]);

        return VendTransaction::unguarded(fn () => VendTransaction::withoutGlobalScopes()->create($attrs + [
            'order_id' => uniqid('T'), 'vend_id' => $vendId, 'transaction_datetime' => '2026-09-21 22:02:00', 'qty' => 1, 'success_qty' => 1,
            'vend_channel_id' => 0, 'vend_channel_code' => 11, 'gst_vat_rate' => 0, 'interface_type' => 0, 'is_found_in_transaction' => true,
            'payment_method_id' => PaymentMethod::where('code', PaymentMethod::CODE_CARD_TERMINAL)->value('id'),
            'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
        ]));
    }
}
