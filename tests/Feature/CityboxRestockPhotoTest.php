<?php

namespace Tests\Feature;

use App\Contracts\Citybox\ChillerGateway;
use App\Jobs\PushCityboxRestockPhotos;
use App\Models\Attachment;
use App\Models\Customer;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\User;
use App\Models\Vend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Support\Citybox\FakeChillerGateway;
use Tests\TestCase;

/**
 * Before/after restock photos on a CityBox chiller stop (Brian, 2026-10-13): the before photo
 * unlocks Open Door (mark1 checks it), the after photo is asked for after Stock In, both stay on
 * the item, and their URLs go to CityBox once their endpoint exists.
 */
class CityboxRestockPhotoTest extends TestCase
{
    use RefreshDatabase;

    private FakeChillerGateway $gw;

    private User $driver;

    private OpsJobItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake(config('filesystems.default'));
        Queue::fake([PushCityboxRestockPhotos::class, \App\Jobs\Vend\SyncVendChannels::class, \App\Jobs\Vend\SaveVendChannelsJson::class]);
        config(['citybox.openapi.enabled' => true, 'citybox.openapi.app_id' => 'A', 'citybox.openapi.secret' => 'S']);
        $this->gw = new FakeChillerGateway;
        $this->app->instance(ChillerGateway::class, $this->gw);
        Permission::findOrCreate('update operations', 'web');
        $this->gw->seedDevice('E1');

        $site = Customer::create(['name' => 'NUS', 'code' => 10001, 'operator_id' => 1, 'status_id' => Customer::STATUS_ACTIVE]);
        $vend = Vend::create(['code' => 6001, 'machine_type' => Vend::MACHINE_TYPE_SMART_CHILLER, 'citybox_equipment_id' => 'E1', 'is_active' => 1, 'operator_id' => 1, 'customer_id' => $site->id]);
        $this->driver = User::factory()->create();
        $job = OpsJob::create(['code' => 900200, 'date' => now()->toDateString(), 'status' => 1, 'delivered_by' => $this->driver->id, 'operator_id' => 1]);
        $this->item = OpsJobItem::create(['ops_job_id' => $job->id, 'vend_id' => $vend->id, 'customer_id' => $site->id, 'status' => OpsJob::STATUS_PICKED]);
    }

    private function upload(string $slot)
    {
        return $this->actingAs($this->driver)->post("/ops-jobs/items/{$this->item->id}/restock-photos", [
            'slot' => $slot,
            'file' => UploadedFile::fake()->image('IMG_0042.jpg', 4032, 3024),
        ], ['Accept' => 'application/json']);
    }

    public function test_open_door_is_refused_until_the_before_photo_is_in(): void
    {
        $this->actingAs($this->driver)->post("/ops-jobs/items/{$this->item->id}/citybox-open-door")
            ->assertSessionHasErrors(['citybox' => 'Take the BEFORE restock photo first — then Open Door.']);
        $this->assertSame([], $this->gw->opens, 'nothing reached CityBox');

        $this->upload('before')->assertOk()->assertJsonCount(1, 'before');
        $this->actingAs($this->driver)->post("/ops-jobs/items/{$this->item->id}/citybox-open-door")->assertSessionHas('success');
        $this->assertCount(1, $this->gw->opens);
    }

    public function test_a_reopen_after_stock_in_is_never_blocked(): void
    {
        $this->item->forceFill(['status' => OpsJob::STATUS_DELIVERED, 'completed_at' => now()])->saveQuietly();
        $this->actingAs($this->driver)->post("/ops-jobs/items/{$this->item->id}/citybox-open-door")->assertSessionHas('success');
    }

    public function test_a_photo_is_shrunk_to_a_jpeg_and_kept_in_its_slot(): void
    {
        $this->upload('after')->assertOk()->assertJsonCount(0, 'before')->assertJsonCount(1, 'after');

        $a = Attachment::sole();
        $this->assertSame([OpsJobItem::PHOTO_RESTOCK_AFTER, 'restock:after', 'IMG_0042.jpg'], [(int) $a->type, $a->desc, $a->name]);
        $this->assertStringEndsWith('.jpg', $a->local_url);
        [$w, $h] = getimagesizefromstring(Storage::get($a->local_url));
        $this->assertSame([1600, 1200], [$w, $h], 'long edge 1600, never the 12 MP original');
    }

    public function test_only_pictures_and_only_the_two_slots_are_accepted(): void
    {
        $this->actingAs($this->driver)->post("/ops-jobs/items/{$this->item->id}/restock-photos", [
            'slot' => 'before', 'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->actingAs($this->driver)->post("/ops-jobs/items/{$this->item->id}/restock-photos", [
            'slot' => 'during', 'file' => UploadedFile::fake()->image('x.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('slot');
    }

    public function test_a_stranger_cannot_add_or_remove_photos(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post("/ops-jobs/items/{$this->item->id}/restock-photos", ['slot' => 'before', 'file' => UploadedFile::fake()->image('x.jpg')])->assertForbidden();

        $this->upload('before');
        $id = Attachment::sole()->id;
        $this->actingAs($stranger)->delete("/ops-jobs/items/{$this->item->id}/restock-photos/{$id}")->assertForbidden();
        $this->actingAs($this->driver)->deleteJson("/ops-jobs/items/{$this->item->id}/restock-photos/{$id}")->assertOk()->assertJsonCount(0, 'before');
        $this->assertSame(0, Attachment::count());
    }

    public function test_the_item_resource_splits_restock_photos_from_ordinary_attachments(): void
    {
        $this->upload('before');
        $this->item->attachments()->create(['full_url' => 'https://spaces/receipt.jpg', 'local_url' => 'sys/receipt.jpg']);

        $data = \App\Http\Resources\OpsJobItemResource::make($this->item->fresh()->load(['attachments', 'vend']))->resolve();
        $this->assertCount(1, $data['attachments']);
        $this->assertCount(1, $data['restock_photos']['before']);
    }

    public function test_photos_go_to_citybox_only_once_their_endpoint_exists_and_both_are_in(): void
    {
        $this->upload('before');
        $this->upload('after');
        Queue::assertNotPushed(PushCityboxRestockPhotos::class);

        config(['citybox.openapi.restock_photo_path' => 'api/Openapi/restock_photo_submit']);
        $this->upload('after');
        Queue::assertPushed(PushCityboxRestockPhotos::class, fn ($job) => $job->opsJobItemId === $this->item->id);

        (new PushCityboxRestockPhotos($this->item->id))->handle($this->gw, app(\App\Services\Citybox\RestockPhotoService::class));
        $this->assertSame(['before', 'after', 'after'], array_column($this->gw->photoSubmits, 'kind'));
        $this->assertSame('ok', $this->item->fresh()->citybox_photo_push_status);
    }
}
