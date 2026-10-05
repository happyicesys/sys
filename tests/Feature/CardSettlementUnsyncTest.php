<?php

namespace Tests\Feature;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\CardTerminal;
use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\RefundTicket;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\CardSettlement\CardSettlementRefundReconciler;
use App\Services\CardSettlement\CardSettlementSyncService;
use App\Services\CardSettlement\CardSettlementUnsyncService;
use App\Support\AutoRefundSource;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Undo sync (Brian, 2026-10-05): a synced NETS report could never be removed,
 * so a wrong or partial file stayed on the books. Undo sync puts it back in
 * review and takes back what its Sync wrote; then it can be deleted.
 */
class CardSettlementUnsyncTest extends TestCase
{
    use RefreshDatabase;

    private const TID = '23100701';

    private const DAY = '2026-09-05';

    private const NEXT = '2026-09-06';

    private Vend $vend;

    private PaymentMethod $card;

    private VendChannelError $ok;

    private VendChannelError $fault;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-09 14:00:00');
        Bus::fake();

        $operator = Operator::create(['code' => 'OP1', 'name' => 'Op', 'gst_vat_rate' => 9, 'timezone' => 'Asia/Singapore']);
        $this->vend = Vend::create(['code' => '9001', 'operator_id' => $operator->id, 'is_active' => 1]);
        $this->card = PaymentMethod::firstOrCreate(['code' => PaymentMethod::CODE_CARD_TERMINAL], ['name' => 'Card Terminal', 'is_active' => true]);
        $this->ok = VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);
        $this->fault = VendChannelError::firstOrCreate(['code' => 4], ['desc' => 'Open circuit, motor not detected (4)']);
        $nets = CardTerminal::create(['name' => 'Nets']);
        CardTerminalUnit::create(['terminal_id' => self::TID, 'card_terminal_id' => $nets->id]);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => self::TID, 'vend_id' => $this->vend->id, 'bound_from' => '2026-08-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function report(string $cutover = self::DAY): CardSettlementReport
    {
        return CardSettlementReport::create([
            'provider' => 'nets', 'original_filename' => "MCONNECT_{$cutover}.csv", 'cutover_date' => $cutover,
            'status' => CardSettlementReport::STATUS_REVIEW,
        ]);
    }

    private function sale(array $overrides = []): VendTransaction
    {
        return VendTransaction::create(array_merge([
            'order_id' => 'ORD-'.uniqid(), 'vend_id' => $this->vend->id, 'transaction_datetime' => self::DAY.' 14:31:07', 'amount' => 240,
            'qty' => 1, 'success_qty' => 1, 'dispensed_qty' => 1, 'vend_channel_id' => 0, 'gst_vat_rate' => 0,
            'payment_method_id' => $this->card->id, 'cashless_mfg' => 'Nets', 'vend_channel_error_id' => $this->ok->id,
            'is_multiple' => false, 'is_found_in_transaction' => true,
        ], $overrides));
    }

    private function line(CardSettlementReport $report, array $overrides = []): CardSettlementRow
    {
        static $n = 0;
        $n++;

        return CardSettlementRow::create(array_merge([
            'card_settlement_report_id' => $report->id, 'row_no' => $n, 'txn_type' => 'Purchase', 'terminal_id' => self::TID,
            'transaction_date' => self::DAY, 'transaction_time' => '14:31:20', 'time_is_partial' => false, 'amount_cents' => 240,
            'fingerprint' => sha1('unsync-'.$n), 'status' => CardSettlementRow::STATUS_MATCHED, 'vend_id' => $this->vend->id,
        ], $overrides));
    }

    /** A purchase line for $sale in $purchaseReport, reversed by a line in $reversalReport. */
    private function reversedLine(VendTransaction $sale, CardSettlementReport $purchaseReport, ?CardSettlementReport $reversalReport = null): CardSettlementRow
    {
        $purchase = $this->line($purchaseReport, ['matched_vend_transaction_id' => $sale->id]);
        $rev = $this->line($reversalReport ?? $purchaseReport, ['amount_cents' => -240, 'is_reversal' => true, 'reverses_row_id' => $purchase->id]);
        $purchase->update(['reversed_by_row_id' => $rev->id]);

        return $purchase;
    }

    private function sync(CardSettlementReport $report): void
    {
        app(CardSettlementSyncService::class)->sync($report->fresh(), null);
    }

    private function unsync(CardSettlementReport $report): array
    {
        return app(CardSettlementUnsyncService::class)->unsync($report->fresh(), null);
    }

    private function user(): User
    {
        foreach (['read card-settlements', 'update card-settlements', 'delete card-settlements'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo(['read card-settlements', 'update card-settlements', 'delete card-settlements']);

        return $user;
    }

    public function test_a_synced_report_is_deleted_only_after_undo_sync(): void
    {
        $report = $this->report();
        $sale = $this->sale();
        $this->line($report, ['matched_vend_transaction_id' => $sale->id]);
        $this->sync($report);
        $this->assertNotNull($sale->fresh()->card_settlement_synced_at);
        $user = $this->user();

        $this->actingAs($user)->delete("/card-settlements/{$report->id}")->assertStatus(422);

        $this->actingAs($user)->post("/card-settlements/{$report->id}/unsync")->assertRedirect()->assertSessionHas('message');
        $report->refresh();
        $this->assertSame(CardSettlementReport::STATUS_REVIEW, $report->status);
        $this->assertNull($report->synced_at);
        $this->assertNull($sale->fresh()->card_settlement_synced_at);
        $this->actingAs($user)->post("/card-settlements/{$report->id}/unsync")->assertStatus(422);

        $this->actingAs($user)->delete("/card-settlements/{$report->id}")->assertRedirect(route('card-settlements'));
        $this->assertNull(CardSettlementReport::find($report->id));
        $this->assertSame(0, CardSettlementRow::count());
        $this->assertNotNull($sale->fresh(), 'the machine sale itself is never touched');
    }

    public function test_undo_removes_the_na_sales_its_sync_created_and_a_resync_brings_them_back(): void
    {
        $report = $this->report();
        $line = $this->line($report, ['status' => CardSettlementRow::STATUS_UNMATCHED, 'resolution_note' => CardSettlementRow::NOTE_NO_SALE_IN_WINDOW]);
        $adoptedLine = $this->line($report, ['status' => CardSettlementRow::STATUS_UNMATCHED, 'resolution_note' => CardSettlementRow::NOTE_NO_SALE_IN_WINDOW, 'transaction_time' => '16:00:00']);
        $this->sync($report);
        $orphan = $line->fresh()->orphanSale;
        $adopted = $adoptedLine->fresh()->orphanSale;
        $this->assertNotNull($orphan);
        $adopted->forceFill(['is_found_in_transaction' => true])->save(); // its TRADE arrived

        $r = $this->unsync($report);

        $this->assertSame(1, $r['orphans_deleted']);
        $this->assertNull(VendTransaction::withoutGlobalScopes()->find($orphan->id), 'an NA sale still awaiting its TRADE goes');
        $line->refresh();
        $this->assertSame(CardSettlementRow::STATUS_UNMATCHED, $line->status);
        $this->assertNull($line->matched_vend_transaction_id);
        $this->assertSame(CardSettlementRow::NOTE_NO_SALE_IN_WINDOW, $line->resolution_note);
        $this->assertNotNull($adopted->fresh(), 'an adopted orphan is a real sale and stays');
        $this->assertNull($adopted->fresh()->card_settlement_synced_at);

        $this->sync($report);
        $this->assertNotNull($line->fresh()->orphanSale, 'a re-Sync creates it again');
    }

    public function test_undo_clears_the_reversal_tick_and_releases_its_ticket(): void
    {
        $report = $this->report();
        $sale = $this->sale(['success_qty' => 0, 'dispensed_qty' => 0, 'vend_channel_error_id' => $this->fault->id]);
        $this->reversedLine($sale, $report);
        $ticket = RefundTicket::create([
            'reference' => 'RF-'.uniqid(), 'vend_code' => '9001', 'vend_id' => $this->vend->id, 'vend_transaction_id' => $sale->id,
            'order_id' => $sale->order_id, 'claimed_amount_cents' => 240, 'status' => RefundTicket::STATUS_SUBMITTED,
        ]);
        $this->sync($report);
        $this->assertSame(AutoRefundSource::SETTLEMENT_REPORT_REVERSAL, $sale->fresh()->auto_refund_source);
        $this->assertTrue((bool) $ticket->fresh()->auto_refund_detected);

        $r = $this->unsync($report);

        $sale->refresh();
        $this->assertFalse((bool) $sale->is_refunded);
        $this->assertNull($sale->auto_refund_source);
        $this->assertNull($sale->card_settlement_state);
        $this->assertSame(1, $r['ticks_cleared']);
        $this->assertFalse((bool) $ticket->fresh()->auto_refund_detected);
    }

    public function test_undoing_one_of_the_two_files_unfinalises_the_day_and_its_na_in_nets_tick(): void
    {
        $today = $this->report(self::DAY);
        $next = $this->report(self::NEXT);
        $failed = $this->sale(['success_qty' => 0, 'dispensed_qty' => 0, 'vend_channel_error_id' => $this->fault->id]);
        $this->sync($today);
        $this->sync($next);
        $failed->refresh();
        $this->assertSame(AutoRefundSource::SETTLEMENT_REPORT_NOT_CAPTURED, $failed->auto_refund_source, 'precondition: NA in NETS');
        $this->assertSame(CardSettlementRefundReconciler::STATE_NOT_CAPTURED, $failed->card_settlement_state);

        $this->unsync($next);

        $failed->refresh();
        $this->assertFalse((bool) $failed->is_refunded, 'the day is no longer final, so no line proves nothing');
        $this->assertNull($failed->card_settlement_state);
        $this->assertFalse(app(CardSettlementRefundReconciler::class)->isDayFinal(Carbon::parse(self::DAY)));

        $this->sync($next);
        $this->assertSame(AutoRefundSource::SETTLEMENT_REPORT_NOT_CAPTURED, $failed->fresh()->auto_refund_source, 'and a re-Sync puts it back');
    }

    public function test_deleting_a_file_whose_reversal_undid_another_files_purchase_clears_that_tick(): void
    {
        $purchaseFile = $this->report(self::DAY);
        $reversalFile = $this->report(self::NEXT);
        $sale = $this->sale(['success_qty' => 0, 'dispensed_qty' => 0, 'vend_channel_error_id' => $this->fault->id]);
        $this->reversedLine($sale, $purchaseFile, $reversalFile);
        $this->sync($purchaseFile);
        $this->sync($reversalFile);
        $this->assertSame(AutoRefundSource::SETTLEMENT_REPORT_REVERSAL, $sale->fresh()->auto_refund_source);

        $this->unsync($reversalFile);
        $this->assertTrue((bool) $sale->fresh()->is_refunded, 'the pairing still stands while the file exists');

        $this->actingAs($this->user())->delete("/card-settlements/{$reversalFile->id}")->assertRedirect();

        $sale->refresh();
        $this->assertFalse((bool) $sale->is_refunded, 'its reversal line is gone, so nothing says the money went back');
        $this->assertNull($sale->auto_refund_source);
    }

    public function test_undo_removes_a_re_vend_link_the_report_proved_but_keeps_one_the_apk_reported(): void
    {
        $today = $this->report(self::DAY);
        $next = $this->report(self::NEXT);
        $failed = $this->sale(['success_qty' => 0, 'dispensed_qty' => 0, 'vend_channel_error_id' => $this->fault->id, 'transaction_datetime' => self::DAY.' 14:00:00']);
        $this->line($today, ['matched_vend_transaction_id' => $failed->id, 'transaction_time' => '14:00:10']);
        $revend = $this->sale(['transaction_datetime' => self::DAY.' 14:05:00']);
        $apkFailed = $this->sale(['success_qty' => 0, 'dispensed_qty' => 0, 'vend_channel_error_id' => $this->fault->id, 'transaction_datetime' => self::DAY.' 09:00:00']);
        $apk = $this->sale(['transaction_datetime' => self::DAY.' 09:01:00', 'vend_transaction_json' => ['CSHL_ARMED_MS' => 1200]]);
        $apk->forceFill(['is_retained_credit_settlement' => true, 'retained_credit_settles_txn_id' => $apkFailed->id])->save();
        $this->sync($today);
        $this->sync($next);
        $this->assertSame($failed->id, (int) $revend->fresh()->retained_credit_settles_txn_id, 'precondition: linked at Sync');

        $r = $this->unsync($next);

        $this->assertSame(1, $r['links_removed']);
        $this->assertFalse((bool) $revend->fresh()->is_retained_credit_settlement);
        $this->assertNull($revend->fresh()->retained_credit_settles_txn_id);
        $this->assertTrue((bool) $apk->fresh()->is_retained_credit_settlement, "the APK's own evidence stands");
        $this->assertSame($apkFailed->id, (int) $apk->fresh()->retained_credit_settles_txn_id);
    }
}
