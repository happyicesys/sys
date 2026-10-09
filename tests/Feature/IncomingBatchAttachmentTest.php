<?php

namespace Tests\Feature;

use App\Models\IncomingBatchAttachment;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Incoming Stock (2026-10-09 ops ask): files kept with a batch, and a signed Total Qty
 * on the history list — ops key stock sent out / adjusted down as negative incoming.
 */
class IncomingBatchAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
    }

    private function viewer(): User
    {
        Permission::findOrCreate('read products', 'web');

        return tap(User::factory()->create())->givePermissionTo('read products');
    }

    private function seedBatch(string $batch, array $qtys): void
    {
        foreach ($qtys as $i => $qty) {
            $product = Product::create(['code' => "IBA{$batch}{$i}", 'name' => "P{$i}"]);
            DB::table('product_movements')->insert([
                'product_id' => $product->id, 'type' => ProductMovement::TYPE_INCOMING, 'qty' => $qty,
                'batch_number' => $batch, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_batch_entry_saves_its_attachments(): void
    {
        $product = Product::create(['code' => 'IBA1', 'name' => 'Tea']);
        $user = $this->viewer();

        $this->actingAs($user)->post('/products/movements/batch-incoming', [
            'batch_number' => 'Benelux #002',
            'created_at' => '2026-10-09',
            'products' => [['id' => $product->id, 'qty' => -5]],
            'attachments' => [
                UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('do.jpg'),
            ],
        ])->assertRedirect(route('product-movements.index'));

        $this->assertSame(-5, (int) ProductMovement::where('batch_number', 'Benelux #002')->value('qty'));
        $files = IncomingBatchAttachment::where('batch_number', 'Benelux #002')->orderBy('id')->get();
        $this->assertSame(['invoice.pdf', 'do.jpg'], $files->pluck('name')->all());
        $this->assertSame($user->id, $files->first()->user_id);
        Storage::assertExists($files->first()->local_url);
    }

    public function test_a_refused_file_saves_nothing(): void
    {
        $product = Product::create(['code' => 'IBA1', 'name' => 'Tea']);

        $this->actingAs($this->viewer())->post('/products/movements/batch-incoming', [
            'batch_number' => 'X1',
            'created_at' => '2026-10-09',
            'products' => [['id' => $product->id, 'qty' => 3]],
            'attachments' => [UploadedFile::fake()->create('run.exe', 10)],
        ])->assertSessionHasErrors('attachments.0');

        $this->assertSame(0, ProductMovement::count());
        $this->assertSame(0, IncomingBatchAttachment::count());
    }

    public function test_history_shows_signed_total_and_attachments(): void
    {
        $this->seedBatch('IN-1', [24, 12]);
        $this->seedBatch('OUT-1', [-3, -2]);
        IncomingBatchAttachment::create(['batch_number' => 'IN-1', 'local_url' => 'a', 'full_url' => 'https://x/a', 'name' => 'inv.pdf']);

        $this->actingAs($this->viewer())
            ->get('/products/movements/incoming-history')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $rows = collect($page->toArray()['props']['history']['data'])->keyBy('batch_number');
                $this->assertSame(36, $rows['IN-1']['total_qty']);
                $this->assertSame(-5, $rows['OUT-1']['total_qty']);
                $this->assertSame(['inv.pdf'], array_column($rows['IN-1']['attachments'], 'name'));
                $this->assertSame([], $rows['OUT-1']['attachments']);
            });
    }

    public function test_attachments_can_be_added_to_and_removed_from_an_existing_batch(): void
    {
        $this->seedBatch('Benelux #001', [48]);
        $user = $this->viewer();

        $this->actingAs($user)->post('/products/movements/incoming-attachments', [
            'batch_number' => 'Benelux #001',
            'attachments' => [UploadedFile::fake()->create('tax-inv.pdf', 50, 'application/pdf')],
        ])->assertRedirect();

        $file = IncomingBatchAttachment::sole();
        $this->assertSame('Benelux #001', $file->batch_number);

        $this->actingAs($user)
            ->get('/products/movements/incoming-history/'.rawurlencode('Benelux #001'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('attachments', 1)->where('attachments.0.name', 'tax-inv.pdf'));

        $this->actingAs($user)->delete('/products/movements/incoming-attachments/'.$file->id)->assertRedirect();
        $this->assertSame(0, IncomingBatchAttachment::count());
        Storage::assertMissing($file->local_url);
    }

    public function test_attachment_needs_an_existing_batch(): void
    {
        $this->actingAs($this->viewer())->post('/products/movements/incoming-attachments', [
            'batch_number' => 'nope',
            'attachments' => [UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')],
        ])->assertNotFound();

        $this->assertSame(0, IncomingBatchAttachment::count());
    }
}
