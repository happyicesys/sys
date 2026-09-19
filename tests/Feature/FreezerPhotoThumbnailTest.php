<?php

namespace Tests\Feature;

use App\Models\FreezerControlCommand;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Models\Vend;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The small copy of a camera still (App\Services\Freezer\FreezerPhotoThumbnail): the panel draws
 * every tile from it, so it has to exist for a freshly uploaded photo AND for the ones that landed
 * before thumbnails did.
 */
class FreezerPhotoThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();
        DB::table('operators')->insert([
            'id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'code' => 'HIPL', 'name' => 'HIPL',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->user = User::factory()->create(['operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID]);
        foreach (['read machine-settings', 'update machine-settings'] as $p) {
            $this->user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        OperatorScope::flush();
        $this->actingAs($this->user);
        Storage::fake();
    }

    private function freezer(): Vend
    {
        $vend = new Vend;
        $vend->forceFill([
            'code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1,
            'operator_id' => OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID, 'vend_model_id' => 1,
            'apk_version_code' => 14, 'private_key' => 'TESTKEY000000001',
        ])->save();

        return $vend->refresh();
    }

    /** A cabinet-shaped still: 1280x720, the size the Zijia host uploads. */
    private function jpeg(): string
    {
        $im = imagecreatetruecolor(1280, 720);
        for ($x = 0; $x < 1280; $x += 8) {
            imagefilledrectangle($im, $x, 0, $x + 4, 720, imagecolorallocate($im, $x % 256, 120, 255 - $x % 256));
        }
        ob_start();
        imagejpeg($im, null, 85);

        return ob_get_clean();
    }

    public function test_an_uploaded_photo_gets_a_small_copy_beside_it(): void
    {
        $vend = $this->freezer();
        $command = FreezerControlCommand::create([
            'vend_id' => $vend->id, 'cmd_id' => 'PH1', 'op' => 'photo', 'args' => ['cameraId' => 4],
            'status' => FreezerControlCommand::STATUS_PENDING,
        ]);

        $jpeg = $this->jpeg();
        $this->post("/api/v1/vends/{$vend->code}/photos", [
            'cmdId' => 'PH1',
            'cameraId' => 4,
            'file' => UploadedFile::fake()->createWithContent('camera.jpg', $jpeg),
        ])->assertOk();

        $command->refresh();
        $this->assertNotNull($command->attachment_thumb_path);
        $this->assertStringEndsWith('-thumb.webp', $command->attachment_thumb_path);
        Storage::assertExists($command->attachment_thumb_path);

        // Smaller in both senses: fewer pixels, far fewer bytes than the still it was made from.
        $thumb = Storage::get($command->attachment_thumb_path);
        $size = getimagesizefromstring($thumb);
        $this->assertSame(320, $size[0]);
        $this->assertSame(180, $size[1]);
        $this->assertLessThan(strlen($jpeg) / 2, strlen($thumb));
    }

    public function test_the_panel_offers_the_small_copy_and_the_full_image(): void
    {
        $vend = $this->freezer();
        $command = FreezerControlCommand::create([
            'vend_id' => $vend->id, 'cmd_id' => 'PH2', 'op' => 'photo', 'args' => ['cameraId' => 3],
            'status' => 'ok', 'attachment_path' => 'freezer-photos/'.$vend->id.'/PH2-cam3.jpg',
            'attachment_type' => FreezerControlCommand::ATTACHMENT_PHOTO,
        ]);
        Storage::put($command->attachment_path, $this->jpeg());

        $photo = $this->getJson("/vends/{$vend->id}/freezer-controls")->json('photos.0');
        $this->assertStringNotContainsString('thumb', $photo['url']);
        $this->assertStringContainsString('thumb=1', $photo['thumb_url']);

        // A photo stored before thumbnails existed gets its small copy on the first ask, and keeps it.
        $this->assertNull($command->fresh()->attachment_thumb_path);
        $this->get($photo['thumb_url'])->assertOk()->assertHeader('content-type', 'image/webp');
        $stored = $command->fresh()->attachment_thumb_path;
        $this->assertNotNull($stored);

        // The second ask serves the same stored file rather than making another.
        $this->get($photo['thumb_url'])->assertOk();
        $this->assertSame($stored, $command->fresh()->attachment_thumb_path);

        // The full image is still the full image, cached hard because a still never changes.
        $full = $this->get($photo['url'])->assertOk();
        $this->assertSame('image/jpeg', $full->headers->get('content-type'));
        $this->assertStringContainsString('immutable', $full->headers->get('cache-control'));
    }

    public function test_a_photo_that_cannot_be_read_falls_back_to_the_full_image(): void
    {
        $vend = $this->freezer();
        $command = FreezerControlCommand::create([
            'vend_id' => $vend->id, 'cmd_id' => 'PH3', 'op' => 'photo', 'args' => ['cameraId' => 3],
            'status' => 'ok', 'attachment_path' => 'freezer-photos/'.$vend->id.'/PH3-cam3.jpg',
            'attachment_type' => FreezerControlCommand::ATTACHMENT_PHOTO,
        ]);
        Storage::put($command->attachment_path, 'not an image at all');

        $this->get(route('vends.freezer-controls.attachment', [$vend->id, $command->id, 'thumb' => 1]))
            ->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->assertNull($command->fresh()->attachment_thumb_path);
    }
}
