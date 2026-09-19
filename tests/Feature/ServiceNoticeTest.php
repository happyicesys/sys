<?php

namespace Tests\Feature;

use App\Jobs\RemoveEmptyOpsJob;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\ServiceNotice;
use App\Models\ServiceNoticeItem;
use App\Models\User;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Service Notice: a repair stop inside an ops job. Covers the gates (permission
 * + operator ceiling on EVERY route — the cms original had neither), the rules
 * kept from cms (one line = one item, no completing with an item still New) and
 * the ones added (Incomplete needs a reason, deleting removes the files).
 */
class ServiceNoticeTest extends TestCase
{
    use RefreshDatabase;

    private Operator $operator;

    private User $user;

    private OpsJob $job;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = Operator::create(['code' => 'OP1', 'name' => 'Operator One']);
        $this->user = $this->userWith($this->operator, ['read', 'create', 'update', 'delete', 'export']);
        $customer = Customer::create(['name' => 'Site A', 'operator_id' => $this->operator->id]);
        $this->vend = Vend::create(['code' => 'V701', 'operator_id' => $this->operator->id, 'customer_id' => $customer->id]);
        $this->job = OpsJob::create([
            'code' => 70001, 'date' => Carbon::today(), 'operator_id' => $this->operator->id,
            'created_by' => $this->user->id, 'delivered_by' => $this->user->id,
        ]);
    }

    private function userWith(Operator $operator, array $actions): User
    {
        $user = User::factory()->create(['operator_id' => $operator->id]);
        foreach ($actions as $action) {
            Permission::findOrCreate($action.' service-notices', 'web');
            $user->givePermissionTo($action.' service-notices');
        }

        return $user;
    }

    private function open(string $items = "Compressor noisy\nDoor seal torn"): ServiceNotice
    {
        $this->actingAs($this->user)
            ->postJson('/ops-jobs/'.$this->job->id.'/service-notices', ['vend_id' => $this->vend->id, 'items' => $items])
            ->assertOk();

        return ServiceNotice::latest('id')->firstOrFail();
    }

    public function test_one_line_becomes_one_item_and_blank_lines_are_dropped(): void
    {
        $notice = $this->open("Compressor noisy\r\n\r\n   \nDoor seal torn\n");

        $this->assertSame(10001, $notice->code);
        $this->assertSame('SN-10001', $notice->display_code);
        $this->assertSame($this->vend->customer_id, $notice->customer_id);
        $this->assertSame(['Compressor noisy', 'Door seal torn'], $notice->items->pluck('desc')->all());
        $this->assertSame([1, 2], $notice->items->pluck('sequence')->all());

        // The running number is per operator and keeps counting.
        $this->assertSame(10002, $this->open('Light out')->code);
    }

    public function test_a_notice_needs_at_least_one_real_item(): void
    {
        $this->actingAs($this->user)
            ->postJson('/ops-jobs/'.$this->job->id.'/service-notices', ['vend_id' => $this->vend->id, 'items' => "  \n\n "])
            ->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertSame(0, ServiceNotice::count());
    }

    public function test_every_route_is_permission_gated(): void
    {
        $notice = $this->open();
        $item = $notice->items->first();
        $nobody = User::factory()->create(['operator_id' => $this->operator->id]);

        $this->actingAs($nobody);
        $this->postJson('/ops-jobs/'.$this->job->id.'/service-notices', ['vend_id' => $this->vend->id, 'items' => 'x'])->assertForbidden();
        $this->get('/service-notices/'.$notice->id.'/edit')->assertForbidden();
        $this->get('/service-notices')->assertForbidden();
        $this->get('/service-notices/excel')->assertForbidden();
        $this->postJson('/service-notices/items/'.$item->id.'/status', ['status' => 2])->assertForbidden();
        $this->postJson('/service-notices/'.$notice->id.'/complete')->assertForbidden();
        $this->deleteJson('/service-notices/'.$notice->id)->assertForbidden();

        $this->assertSame(ServiceNoticeItem::STATUS_NEW, $item->fresh()->status);
    }

    public function test_a_driver_can_work_a_notice_but_not_delete_it(): void
    {
        $notice = $this->open();
        $driver = $this->userWith($this->operator, ['read', 'create', 'update']);

        $this->actingAs($driver);
        $this->postJson('/service-notices/items/'.$notice->items->first()->id.'/status', ['status' => ServiceNoticeItem::STATUS_COMPLETED])->assertOk();
        $this->deleteJson('/service-notices/items/'.$notice->items->first()->id)->assertForbidden();
        $this->deleteJson('/service-notices/'.$notice->id)->assertForbidden();

        $this->assertNotNull($notice->fresh());
    }

    public function test_another_operators_notice_does_not_exist_for_a_scoped_viewer(): void
    {
        $notice = $this->open();
        $item = $notice->items->first();

        // Operator 1 sees everything, so the scoped viewer must be someone else.
        $other = Operator::create(['code' => 'OP2', 'name' => 'Operator Two']);
        $this->assertNotSame(1, $other->id);
        $outsider = $this->userWith($other, ['read', 'create', 'update', 'delete']);

        $this->actingAs($outsider);
        $this->get('/service-notices/'.$notice->id.'/edit')->assertNotFound();
        $this->postJson('/ops-jobs/'.$this->job->id.'/service-notices', ['vend_id' => $this->vend->id, 'items' => 'x'])->assertNotFound();
        $this->postJson('/service-notices/items/'.$item->id.'/status', ['status' => 2])->assertNotFound();
        $this->postJson('/service-notices/items/'.$item->id.'/update', ['desc' => 'hijacked'])->assertNotFound();
        $this->deleteJson('/service-notices/'.$notice->id)->assertNotFound();

        $this->assertSame('Compressor noisy', $item->fresh()->desc);
    }

    public function test_only_the_four_real_verdicts_are_accepted(): void
    {
        $item = $this->open()->items->first();

        // cms wrote whatever integer the client sent.
        $this->actingAs($this->user)
            ->postJson('/service-notices/items/'.$item->id.'/status', ['status' => 7])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_incomplete_needs_a_reason(): void
    {
        $item = $this->open()->items->first();
        $url = '/service-notices/items/'.$item->id.'/status';

        $this->actingAs($this->user);
        $this->postJson($url, ['status' => ServiceNoticeItem::STATUS_INCOMPLETE])
            ->assertStatus(422)->assertJsonValidationErrors('incomplete_reason');

        $this->postJson($url, ['status' => ServiceNoticeItem::STATUS_INCOMPLETE, 'incomplete_reason' => 'No spare seal on the van'])->assertOk();

        $item->refresh();
        $this->assertSame(ServiceNoticeItem::STATUS_INCOMPLETE, $item->status);
        $this->assertSame('No spare seal on the van', $item->incomplete_reason);
        $this->assertSame($this->user->id, $item->status_changed_by);

        // Moving on to Done drops the stale reason.
        $this->postJson($url, ['status' => ServiceNoticeItem::STATUS_COMPLETED])->assertOk();
        $this->assertNull($item->fresh()->incomplete_reason);
    }

    public function test_a_notice_cannot_be_completed_while_an_item_is_still_new(): void
    {
        $notice = $this->open();
        [$first, $second] = $notice->items->all();

        $this->actingAs($this->user);
        $this->postJson('/service-notices/items/'.$first->id.'/status', ['status' => ServiceNoticeItem::STATUS_COMPLETED])->assertOk();

        $this->postJson('/service-notices/'.$notice->id.'/complete')
            ->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertTrue($notice->fresh()->isPending());

        // Cancelled counts as a verdict, as it did in cms.
        $this->postJson('/service-notices/items/'.$second->id.'/status', ['status' => ServiceNoticeItem::STATUS_CANCELLED])->assertOk();
        $this->postJson('/service-notices/'.$notice->id.'/complete')->assertOk()
            ->assertJsonPath('serviceNotice.status_name', 'Completed')
            ->assertJsonPath('serviceNotice.items_resolved_count', 2);

        $notice->refresh();
        $this->assertTrue($notice->isCompleted());
        $this->assertSame($this->user->id, $notice->completed_by);
    }

    public function test_a_completed_notice_is_locked_until_reopened(): void
    {
        $notice = $this->open('Light out');
        $item = $notice->items->first();

        $this->actingAs($this->user);
        $this->postJson('/service-notices/items/'.$item->id.'/status', ['status' => ServiceNoticeItem::STATUS_COMPLETED])->assertOk();
        $this->postJson('/service-notices/'.$notice->id.'/complete')->assertOk();

        $this->postJson('/service-notices/items/'.$item->id.'/update', ['desc' => 'rewritten'])->assertStatus(422);
        $this->postJson('/service-notices/'.$notice->id.'/items', ['desc' => 'one more'])->assertStatus(422);
        $this->assertSame('Light out', $item->fresh()->desc);

        $this->postJson('/service-notices/'.$notice->id.'/undo-complete')->assertOk();
        $notice->refresh();
        $this->assertTrue($notice->isPending());
        $this->assertNull($notice->completed_at);
        $this->assertSame($this->user->id, $notice->undo_completed_by);

        $this->postJson('/service-notices/'.$notice->id.'/items', ['desc' => 'one more'])->assertOk();
        $this->assertSame(2, $notice->items()->count());
    }

    public function test_clearing_a_description_never_deletes_the_item(): void
    {
        $item = $this->open()->items->first();

        // The cms autosave silently deleted the row when its text was cleared.
        $this->actingAs($this->user)
            ->postJson('/service-notices/items/'.$item->id.'/update', ['desc' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('desc');

        $this->assertSame('Compressor noisy', $item->fresh()->desc);
    }

    public function test_photos_go_into_their_slot_and_deleting_the_notice_removes_the_files(): void
    {
        Storage::fake();
        $notice = $this->open('Door seal torn');
        $item = $notice->items->first();

        $this->actingAs($this->user);
        $this->post('/service-notices/items/'.$item->id.'/attachments', [
            'slot' => ServiceNoticeItem::SLOT_BEFORE,
            'file' => UploadedFile::fake()->image('before.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->post('/service-notices/items/'.$item->id.'/attachments', [
            'slot' => 9, 'file' => UploadedFile::fake()->image('x.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->post('/service-notices/items/'.$item->id.'/attachments', [
            'slot' => ServiceNoticeItem::SLOT_AFTER, 'file' => UploadedFile::fake()->create('run.sh', 4, 'text/x-shellscript'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $attachment = $item->attachments()->firstOrFail();
        $this->assertSame(ServiceNoticeItem::SLOT_BEFORE, (int) $attachment->type);
        $this->assertLessThanOrEqual(255, strlen($attachment->full_url));
        Storage::assertExists($attachment->local_url);

        $this->deleteJson('/service-notices/'.$notice->id)->assertOk();

        Storage::assertMissing($attachment->local_url);
        $this->assertSame(0, ServiceNotice::count());
        $this->assertSame(0, ServiceNoticeItem::count());
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function test_an_attachment_of_another_model_cannot_be_deleted_through_an_item(): void
    {
        Storage::fake();
        $item = $this->open()->items->first();
        $foreign = \App\Models\Attachment::create([
            'modelable_type' => Vend::class, 'modelable_id' => $this->vend->id,
            'full_url' => 'https://x/y.jpg', 'local_url' => 'y.jpg',
        ]);

        $this->actingAs($this->user)
            ->deleteJson('/service-notices/items/'.$item->id.'/attachments/'.$foreign->id)
            ->assertNotFound();

        $this->assertNotNull($foreign->fresh());
    }

    public function test_the_list_is_cut_by_the_viewers_operator_and_filters_incomplete(): void
    {
        $mine = $this->open('Light out');
        $this->actingAs($this->user)->postJson(
            '/service-notices/items/'.$mine->items->first()->id.'/status',
            ['status' => ServiceNoticeItem::STATUS_INCOMPLETE, 'incomplete_reason' => 'no part']
        )->assertOk();
        $this->open('Fan noisy');

        $this->actingAs($this->user)->get('/service-notices?has_incomplete=true')
            ->assertInertia(fn ($page) => $page->component('ServiceNotice/Index')
                ->has('serviceNotices.data', 1)
                ->where('serviceNotices.data.0.display_code', $mine->display_code)
                ->where('serviceNotices.data.0.items_incomplete_count', 1));

        $other = Operator::create(['code' => 'OP2', 'name' => 'Operator Two']);
        $this->actingAs($this->userWith($other, ['read']))->get('/service-notices')
            ->assertInertia(fn ($page) => $page->has('serviceNotices.data', 0));
    }

    public function test_the_nightly_cleanup_keeps_a_job_that_only_holds_a_notice(): void
    {
        $this->job->update(['date' => Carbon::yesterday()]);
        $this->open('Light out');
        $empty = OpsJob::create([
            'code' => 70002, 'date' => Carbon::yesterday(), 'operator_id' => $this->operator->id, 'created_by' => $this->user->id,
        ]);

        (new RemoveEmptyOpsJob(Carbon::yesterday()->toDateString()))->handle();

        $this->assertNotNull($this->job->fresh(), 'a job holding a service notice is not empty');
        $this->assertNull($empty->fresh());
        $this->assertSame(1, ServiceNotice::count());
    }
}
