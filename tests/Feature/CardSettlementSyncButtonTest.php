<?php

namespace Tests\Feature;

use App\Models\CardSettlementReport;
use App\Models\CardSettlementRow;
use App\Models\User;
use App\Models\VendTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * `pending_sync_count` on the report page — the prop the Sync button hides
 * behind once the report is synced and there is nothing left to stamp
 * (Brian, 2026-09-12: "if already synced, this sync button no need to show").
 *
 * It counts real work, not the report's status: a line resolved by hand after
 * the sync, or a Rematch, puts the button straight back.
 */
class CardSettlementSyncButtonTest extends TestCase
{
    use RefreshDatabase;

    private const TID = '23100719';

    private function staff(): User
    {
        foreach (['read card-settlements', 'update card-settlements'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user = User::factory()->create();
        $user->givePermissionTo(['read card-settlements', 'update card-settlements']);

        return $user;
    }

    private function report(string $status): CardSettlementReport
    {
        return CardSettlementReport::create([
            'provider' => 'nets',
            'original_filename' => 'sync-button.csv',
            'cutover_date' => '2026-09-08',
            'status' => $status,
        ]);
    }

    /** A matched line and the sale it claims, stamped or not. */
    private function matchedRow(CardSettlementReport $report, bool $synced): CardSettlementRow
    {
        static $n = 0;
        $n++;

        $txn = VendTransaction::create([
            'order_id' => 'SYNCBTN-'.$n.'-'.uniqid(),
            'vend_id' => 1320,
            'transaction_datetime' => '2026-09-08 22:31:07',
            'amount' => 240,
            'qty' => 1,
            'success_qty' => 1,
            'dispensed_qty' => 1,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
        ]);

        // Not fillable — the sync service stamps it with a query update.
        if ($synced) {
            VendTransaction::withoutGlobalScopes()->whereKey($txn->id)
                ->update(['card_settlement_synced_at' => now()]);
        }

        return CardSettlementRow::create([
            'card_settlement_report_id' => $report->id,
            'row_no' => $n,
            'txn_type' => 'Purchase',
            'terminal_id' => self::TID,
            'transaction_date' => '2026-09-08',
            'transaction_time' => '22:31:05',
            'time_is_partial' => false,
            'amount_cents' => 240,
            'fingerprint' => sha1('sync-button-'.$n.uniqid()),
            'status' => CardSettlementRow::STATUS_MATCHED,
            'vend_id' => 1320,
            'matched_vend_transaction_id' => $txn->id,
        ]);
    }

    public function test_a_fully_synced_report_has_nothing_pending(): void
    {
        $report = $this->report(CardSettlementReport::STATUS_SYNCED);
        $this->matchedRow($report, synced: true);
        $this->matchedRow($report, synced: true);

        $this->actingAs($this->staff())
            ->get('/card-settlements/'.$report->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('report.pending_sync_count', 0));
    }

    public function test_a_line_matched_by_hand_after_the_sync_is_pending_again(): void
    {
        $report = $this->report(CardSettlementReport::STATUS_SYNCED);
        $this->matchedRow($report, synced: true);
        $this->matchedRow($report, synced: false);

        $this->actingAs($this->staff())
            ->get('/card-settlements/'.$report->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('report.pending_sync_count', 1));
    }

    public function test_a_report_still_in_review_is_pending(): void
    {
        $report = $this->report(CardSettlementReport::STATUS_REVIEW);
        $this->matchedRow($report, synced: false);

        $this->actingAs($this->staff())
            ->get('/card-settlements/'.$report->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('report.pending_sync_count', 1));
    }
}
