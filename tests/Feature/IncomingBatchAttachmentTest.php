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
        foreach ([1 => 'HIPL', 2 => 'OPB'] as $id => $code) {
            DB::table('operators')->insert(['id' => $id, 'code' => $code, 'name' => $code, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function viewer(): User
    {
        Permission::findOrCreate('read products', 'web');

        return tap(User::factory()->create())->givePermissionTo('read products');
    }

    /** A user of this operator with these `products` verbs. */
    private function staff(int $operatorId, array $verbs = ['read', 'update', 'delete']): User
    {
        $user = User::factory()->create(['operator_id' => $operatorId]);
        foreach ($verbs as $verb) {
            $user->givePermissionTo(Permission::findOrCreate("{$verb} products", 'web'));
        }

        return $user;
    }

    private function seedBatch(string $batch, array $qtys, ?int $operatorId = null): void
    {
        foreach ($qtys as $i => $qty) {
            $product = Product::create(['code' => "IBA{$batch}{$i}", 'name' => "P{$i}"]);
            DB::table('product_movements')->insert([
                'product_id' => $product->id, 'type' => ProductMovement::TYPE_INCOMING, 'qty' => $qty,
                'batch_number' => $batch, 'operator_id' => $operatorId, 'created_at' => now(), 'updated_at' => now(),
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
        $user = $this->staff(1);

        $this->actingAs($user)->post('/products/movements/incoming-attachments', [
            'batch_number' => 'Benelux #001',
            'attachments' => [UploadedFile::fake()->create('tax-inv.pdf', 50, 'application/pdf')],
        ])->assertRedirect();

        $file = IncomingBatchAttachment::sole();
        $this->assertSame('Benelux #001', $file->batch_number);
        // Supplier documents carry cost prices: private on Spaces, served only through the authed route.
        $this->assertSame('private', Storage::getVisibility($file->local_url));
        $this->assertSame(route('product-movements.incoming-attachments.show', $file->id), $file->full_url);
        $this->actingAs($user)->get(route('product-movements.incoming-attachments.show', $file->id))->assertOk();

        $this->actingAs($user)
            ->get('/products/movements/incoming-history/'.rawurlencode('Benelux #001'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('attachments', 1)->where('attachments.0.name', 'tax-inv.pdf'));

        $this->actingAs($user)->delete('/products/movements/incoming-attachments/'.$file->id)->assertRedirect();
        $this->assertSame(0, IncomingBatchAttachment::count());
        Storage::assertMissing($file->local_url);
    }

    /** Audit UI-02 (2026-10-17): read alone cannot add or remove a batch's files. */
    public function test_read_alone_cannot_add_or_delete_attachments(): void
    {
        $this->seedBatch('B-1', [10], 1);
        $file = IncomingBatchAttachment::create(['batch_number' => 'B-1', 'local_url' => 'sys/incoming-batches/a.pdf', 'name' => 'a.pdf', 'operator_id' => 1]);
        Storage::put('sys/incoming-batches/a.pdf', 'x');

        $this->actingAs($this->staff(1, ['read']))->post('/products/movements/incoming-attachments', [
            'batch_number' => 'B-1', 'attachments' => [UploadedFile::fake()->create('b.pdf', 5, 'application/pdf')],
        ])->assertForbidden();
        $this->actingAs($this->staff(1, ['read', 'update']))->delete('/products/movements/incoming-attachments/'.$file->id)->assertForbidden();

        $this->assertSame(1, IncomingBatchAttachment::count());
        Storage::assertExists('sys/incoming-batches/a.pdf');
    }

    /** Audit UI-02: another operator can neither see, open, add to nor delete a batch's files. */
    public function test_another_operator_cannot_reach_a_batchs_files(): void
    {
        $this->seedBatch('HIPL-7', [10], 1);
        $hiplUser = $this->staff(1);
        $this->actingAs($hiplUser)->post('/products/movements/incoming-attachments', [
            'batch_number' => 'HIPL-7', 'attachments' => [UploadedFile::fake()->create('inv.pdf', 5, 'application/pdf')],
        ])->assertRedirect();
        $file = IncomingBatchAttachment::sole();
        $this->assertSame(1, (int) $file->operator_id);

        $outsider = $this->staff(2);
        $this->actingAs($outsider)->get(route('product-movements.incoming-attachments.show', $file->id))->assertNotFound();
        $this->actingAs($outsider)->delete('/products/movements/incoming-attachments/'.$file->id)->assertNotFound();
        $this->actingAs($outsider)->post('/products/movements/incoming-attachments', [
            'batch_number' => 'HIPL-7', 'attachments' => [UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')],
        ])->assertNotFound();
        $this->actingAs($outsider)->get('/products/movements/incoming-history')
            ->assertInertia(fn (AssertableInertia $page) => $this->assertSame([],
                array_column(collect($page->toArray()['props']['history']['data'])->firstWhere('batch_number', 'HIPL-7')['attachments'] ?? [], 'name')));

        $this->assertSame(1, IncomingBatchAttachment::withoutGlobalScopes()->count());
        Storage::assertExists($file->local_url);
    }

    public function test_attachment_needs_an_existing_batch(): void
    {
        $this->actingAs($this->staff(1))->post('/products/movements/incoming-attachments', [
            'batch_number' => 'nope',
            'attachments' => [UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')],
        ])->assertNotFound();

        $this->assertSame(0, IncomingBatchAttachment::count());
    }
}
