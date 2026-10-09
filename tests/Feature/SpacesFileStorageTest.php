<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\CommissionSettlement;
use App\Models\CommissionSettlementExport;
use App\Models\Operator;
use App\Models\ProductMapping;
use App\Models\RefundPayoutBatch;
use App\Models\RefundSettlementExport;
use App\Models\RefundTicket;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Services\Refund\RefundSettlementService;
use App\Support\PayoutFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Every stored file goes to DO Spaces (2026-10-09 audit):
 *  - deleting an attachment removes its file from the default disk (Spaces),
 *    not the local `public` disk — unless another row still uses the file;
 *  - replicating a product mapping copies its files on the default disk, so the
 *    replica and the original no longer share one file;
 *  - bank payout files are written PRIVATE to the payout disk, downloads fall
 *    back to 'local' for files from before the move, and
 *    `payout-files:migrate-to-spaces` moves them with a verified copy.
 */
class SpacesFileStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.default' => 'digitaloceanspaces',
            'filesystems.disks.digitaloceanspaces.key' => 'test-key',
            'filesystems.disks.digitaloceanspaces.secret' => 'test-secret',
            'refund.payout_files_disk' => 'digitaloceanspaces',
        ]);
        Storage::fake('digitaloceanspaces');
        Storage::fake('local');
        Storage::fake('public');
    }

    private function hipl(): Operator
    {
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID,
            'code' => 'HIPL', 'name' => 'HIPL', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Operator::withoutGlobalScopes()->findOrFail(OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID);
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create(['operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID]);
        foreach ($permissions as $p) {
            $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }

        return $user;
    }

    private function attachmentOn(ProductMapping $mapping, string $path): Attachment
    {
        Storage::put($path, 'png-bytes', 'public');

        return $mapping->attachments()->create([
            'local_url' => $path,
            'full_url' => Storage::url($path),
            'name' => 'menu.png',
        ]);
    }

    // ---------------------------------------------------------- attachments

    public function test_deleting_an_attachment_removes_its_file_from_spaces(): void
    {
        $hipl = $this->hipl();
        $mapping = ProductMapping::withoutGlobalScopes()->create(['name' => 'M1', 'operator_id' => $hipl->id, 'is_active' => true]);
        $attachment = $this->attachmentOn($mapping, 'sys/product-mappings/a.png');

        $this->actingAs($this->userWith('read product-mappings'))
            ->delete('/attachments/'.$attachment->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('digitaloceanspaces')->assertMissing('sys/product-mappings/a.png');
    }

    public function test_deleting_an_attachment_keeps_a_file_another_row_still_uses(): void
    {
        $hipl = $this->hipl();
        $original = ProductMapping::withoutGlobalScopes()->create(['name' => 'M1', 'operator_id' => $hipl->id, 'is_active' => true]);
        $replica = ProductMapping::withoutGlobalScopes()->create(['name' => 'M1-replicated', 'operator_id' => $hipl->id, 'is_active' => true]);
        $kept = $this->attachmentOn($original, 'sys/product-mappings/shared.png');
        // The pre-fix replicate left rows like this: same file, two owners.
        $shared = $replica->attachments()->create(['local_url' => $kept->local_url, 'full_url' => $kept->full_url, 'name' => 'menu.png']);

        $this->actingAs($this->userWith('read product-mappings'))->delete('/attachments/'.$shared->id);

        $this->assertDatabaseMissing('attachments', ['id' => $shared->id]);
        Storage::disk('digitaloceanspaces')->assertExists('sys/product-mappings/shared.png');

        // The last owner going takes the file with it.
        $this->actingAs($this->userWith('read product-mappings'))->delete('/attachments/'.$kept->id);
        Storage::disk('digitaloceanspaces')->assertMissing('sys/product-mappings/shared.png');
    }

    public function test_an_attachment_storing_a_url_as_its_path_leaves_the_file_alone(): void
    {
        // Product thumbnails store the full URL in local_url; there is no key to delete.
        Storage::put('sys/products/thumb.png', 'png-bytes', 'public');
        $attachment = Attachment::create([
            'modelable_type' => ProductMapping::class, 'modelable_id' => 1,
            'local_url' => 'https://happyice-space.sgp1.digitaloceanspaces.com/sys/products/thumb.png',
            'full_url' => 'https://happyice-space.sgp1.digitaloceanspaces.com/sys/products/thumb.png',
        ]);

        $this->assertFalse($attachment->deleteFileIfUnshared());
        Storage::disk('digitaloceanspaces')->assertExists('sys/products/thumb.png');
    }

    public function test_replicating_a_mapping_copies_its_files_on_spaces(): void
    {
        $hipl = $this->hipl();
        $source = ProductMapping::withoutGlobalScopes()->create(['name' => 'M1', 'operator_id' => $hipl->id, 'is_active' => true]);
        $attachment = $this->attachmentOn($source, 'sys/product-mappings/src.png');

        $this->actingAs($this->userWith('read product-mappings'))
            ->post('/product-mappings/replicate', ['id' => $source->id]);

        $replica = ProductMapping::withoutGlobalScopes()->where('name', 'M1-replicated')->firstOrFail();
        $copy = $replica->attachments()->firstOrFail();

        $this->assertNotSame($attachment->local_url, $copy->local_url);
        $this->assertStringStartsWith('sys/product-mappings/', $copy->local_url);
        $this->assertSame(Storage::disk('digitaloceanspaces')->url($copy->local_url), $copy->full_url);
        $this->assertSame('png-bytes', Storage::disk('digitaloceanspaces')->get($copy->local_url));
        $this->assertSame('menu.png', $copy->name);

        // Independent: deleting the replica's image leaves the original's.
        $this->actingAs($this->userWith('read product-mappings'))->delete('/attachments/'.$copy->id);
        Storage::disk('digitaloceanspaces')->assertMissing($copy->local_url);
        Storage::disk('digitaloceanspaces')->assertExists($attachment->local_url);
    }

    // --------------------------------------------------------- payout files

    public function test_paypal_worklist_is_stored_private_on_spaces_and_downloads(): void
    {
        $this->hipl();
        $settlement = RefundPayoutBatch::create([
            'reference' => 'RST-261009-HIPL-01', 'is_settlement' => true, 'settlement_date' => '2026-10-09',
            'operator_id' => 1, 'sequence' => 1, 'method' => 'paypal', 'status' => RefundPayoutBatch::STATUS_OPEN,
        ]);
        RefundTicket::create([
            'reference' => 'RF-000001', 'operator_id' => 1, 'refund_method' => RefundTicket::METHOD_PAYPAL,
            'payout_destination' => 'customer@example.com', 'contact_email' => 'customer@example.com',
            'final_refund_amount_cents' => 250, 'status' => RefundTicket::STATUS_SCHEDULED, 'payout_batch_id' => $settlement->id,
        ]);

        $res = app(RefundSettlementService::class)->exportXlsx($settlement, null, 'Admin');

        $spaces = Storage::disk('digitaloceanspaces');
        $spaces->assertExists($res['path']);
        $this->assertSame('private', $spaces->getVisibility($res['path']));
        Storage::disk('local')->assertMissing($res['path']);
        $this->assertStringStartsWith('PK', $spaces->get($res['path'])); // a real .xlsx (zip)

        $export = RefundSettlementExport::firstOrFail();
        $this->actingAs($this->userWith('payout refunds'))
            ->get("/refund-settlements/{$settlement->id}/exports/{$export->id}/download")
            ->assertOk()
            ->assertDownload('RST-261009-HIPL-01-paypal.xlsx');
    }

    public function test_a_payout_file_from_before_the_move_still_downloads_from_local(): void
    {
        $operator = Operator::create(['code' => 'HIPL', 'name' => 'Happy Ice', 'is_active' => true]);
        $settlement = CommissionSettlement::create([
            'reference' => 'CST-260924-HIPL-01', 'settlement_date' => '2026-09-24', 'operator_id' => $operator->id,
            'sequence' => 1, 'status' => CommissionSettlement::STATUS_OPEN, 'count' => 1, 'total_cents' => 4000,
        ]);
        $path = 'commission-payouts/CST-260924-HIPL-01-cimb.txt';
        Storage::disk('local')->put($path, 'legacy-cimb');
        $export = CommissionSettlementExport::create([
            'commission_settlement_id' => $settlement->id, 'format' => CommissionSettlementExport::FORMAT_CIMB_TXT,
            'file_path' => $path, 'count' => 1, 'total_cents' => 4000, 'exported_at' => now(),
        ]);

        $this->assertSame('local', PayoutFiles::locate($path));
        $response = $this->actingAs($this->userWith('admin-access customers'))
            ->get("/site-settlements/{$settlement->id}/exports/{$export->id}/download");
        $response->assertOk()->assertDownload('CST-260924-HIPL-01-cimb.txt');
        $this->assertSame('legacy-cimb', $response->streamedContent());

        // A file on neither disk is a 404, not a 500.
        $export->update(['file_path' => 'commission-payouts/gone.txt']);
        $this->actingAs($this->userWith('admin-access customers'))
            ->get("/site-settlements/{$settlement->id}/exports/{$export->id}/download")
            ->assertNotFound();
    }

    public function test_migrate_command_moves_payout_files_private_and_verified(): void
    {
        $local = Storage::disk('local');
        $spaces = Storage::disk('digitaloceanspaces');
        $local->put('refund-payouts/RST-261007-HIPL-01-cimb.txt', 'refund-bytes');
        $local->put('commission-payouts/CST-260924-HIPL-01-cimb.txt', 'commission-bytes');
        $local->put('refund-attachments/9/photo.jpg', 'not-a-payout-file');

        $this->artisan('payout-files:migrate-to-spaces --dry-run')->assertSuccessful();
        $spaces->assertMissing('refund-payouts/RST-261007-HIPL-01-cimb.txt');

        $this->artisan('payout-files:migrate-to-spaces')->assertSuccessful();
        $this->assertSame('refund-bytes', $spaces->get('refund-payouts/RST-261007-HIPL-01-cimb.txt'));
        $this->assertSame('private', $spaces->getVisibility('refund-payouts/RST-261007-HIPL-01-cimb.txt'));
        $this->assertSame('commission-bytes', $spaces->get('commission-payouts/CST-260924-HIPL-01-cimb.txt'));
        $local->assertExists('refund-payouts/RST-261007-HIPL-01-cimb.txt'); // kept until --delete-local
        $spaces->assertMissing('refund-attachments/9/photo.jpg');

        $this->artisan('payout-files:migrate-to-spaces --delete-local')->assertSuccessful();
        $local->assertMissing('refund-payouts/RST-261007-HIPL-01-cimb.txt');
        $local->assertMissing('commission-payouts/CST-260924-HIPL-01-cimb.txt');
        $local->assertExists('refund-attachments/9/photo.jpg');
        $this->assertSame('digitaloceanspaces', PayoutFiles::locate('refund-payouts/RST-261007-HIPL-01-cimb.txt'));
    }

    public function test_migrate_command_keeps_local_when_spaces_holds_different_bytes(): void
    {
        Storage::disk('local')->put('refund-payouts/X-cimb.txt', 'old');
        Storage::disk('digitaloceanspaces')->put('refund-payouts/X-cimb.txt', 'new');

        $this->artisan('payout-files:migrate-to-spaces --delete-local')->assertFailed();

        Storage::disk('local')->assertExists('refund-payouts/X-cimb.txt');
        $this->assertSame('new', Storage::disk('digitaloceanspaces')->get('refund-payouts/X-cimb.txt'));
    }
}
