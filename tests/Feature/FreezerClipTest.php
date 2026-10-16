<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyDeviceSignature;
use App\Models\Operator;
use App\Models\SmartFreezerClip;
use App\Models\SmartFreezerRecognition;
use App\Models\SmartFreezerVideo;
use App\Models\User;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Our own camera-5 door-session recordings (Brian, 2026-10-13): the freezer uploads one mp4 per
 * sale on a signed route, mark1 keeps it private and shows a gated play link on Sales Transactions
 * and AI Recognition. Never a Zijia video — nothing here reaches their AI.
 */
class FreezerClipTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'TESTKEY000000001';

    private const REF = 'SF-50001-1791539003-2';

    private Operator $operator;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));

        $this->operator = Operator::withoutGlobalScopes()->create(['code' => 'TSTOP', 'name' => 'Test Operator', 'is_active' => true]);
        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Freezer site', 'profile_id' => 1, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->vend = new Vend;
        $this->vend->forceFill([
            'code' => 50001, 'name' => 'Freezer 50001', 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER,
            'is_active' => 1, 'operator_id' => $this->operator->id, 'customer_id' => $customerId,
            'vend_model_id' => 1, 'private_key' => self::KEY, 'is_testing' => 0,
        ])->save();
    }

    /** A tiny but well-formed ISO BMFF header: size + `ftyp` + brand. */
    private function mp4(int $padTo = 2048): string
    {
        return str_pad(pack('N', 24).'ftypisom'.pack('N', 512).'isomiso2', $padTo, "\0");
    }

    private function upload(string $body, string $ref = self::REF, int $camera = 5, array $headers = [], ?string $key = self::KEY): TestResponse
    {
        $path = "/api/v1/vends/50001/clips/{$ref}/{$camera}";
        $ts = (string) time();
        $headers += [
            'X-Device-Timestamp' => $ts,
            'X-Device-Signature' => VerifyDeviceSignature::sign($key ?? 'nope', 'POST', $path, $ts, $body),
            'Content-Type' => 'video/mp4',
            'Accept' => 'application/json',
        ];

        return $this->call('POST', $path, [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    private function viewer(): User
    {
        Permission::findOrCreate('read ai-recognition', 'web');
        Permission::findOrCreate('read transactions', 'web');
        $user = User::factory()->create(['operator_id' => $this->operator->id]);
        $user->givePermissionTo(['read ai-recognition', 'read transactions']);
        OperatorScope::flush();

        return $user;
    }

    public function test_a_signed_upload_is_stored_privately_and_a_re_upload_replaces_it(): void
    {
        $this->upload($this->mp4(), headers: [
            'X-Clip-Started-At' => '1791539003500', 'X-Clip-Door-Closed-At' => '1791539031000',
            'X-Clip-Duration-Ms' => '30500', 'X-Clip-Meta' => json_encode(['pieces' => 3, 'gaps_ms' => 2600]),
        ])->assertCreated()->assertJsonPath('bytes', 2048);

        $clip = SmartFreezerClip::sole();
        $this->assertSame("freezer-videos/{$this->vend->id}/".self::REF.'-cam5.mp4', $clip->path);
        $this->assertSame(5, $clip->camera_id);
        $this->assertSame(30500, $clip->duration_ms, 'no ffprobe on a fake file: the device figure stands');
        $this->assertSame(['pieces' => 3, 'gaps_ms' => 2600], $clip->meta);
        $this->assertSame('2026-10-09 17:43:23', $clip->recorded_from->format('Y-m-d H:i:s'));
        Storage::disk()->assertExists($clip->path);
        $this->assertSame('private', Storage::disk()->getVisibility($clip->path));

        $this->upload($this->mp4(4096))->assertCreated();
        $this->assertSame(1, SmartFreezerClip::count());
        $this->assertSame(4096, SmartFreezerClip::sole()->bytes);
        $this->assertSame(0, SmartFreezerVideo::count(), 'never a Zijia video, so never sent to their AI');
    }

    public function test_the_settings_switch_names_the_camera_or_turns_recording_off(): void
    {
        $get = function (?string $key = self::KEY) {
            $path = '/api/v1/vends/50001/clips/settings';
            $ts = (string) time();

            return $this->call('GET', $path, [], [], [], $this->transformHeadersToServerVars([
                'X-Device-Timestamp' => $ts, 'Accept' => 'application/json',
                'X-Device-Signature' => VerifyDeviceSignature::sign($key, 'GET', $path, $ts, ''),
            ]));
        };

        $get()->assertOk()->assertExactJson(['camera' => 5, 'segment_ms' => 10000, 'max_ms' => 300000, 'guard_cameras' => []]);
        $get('WRONGKEY00000000')->assertStatus(401);

        config(['smart_freezer.clips.vends' => ['2009']]);
        $get()->assertOk()->assertJsonPath('camera', null);
        config(['smart_freezer.clips.vends' => ['2009', '50001']]);
        $get()->assertOk()->assertJsonPath('camera', 5);

        config(['smart_freezer.clips.vends' => ['2009:3', '50001:4']]);
        $get()->assertOk()->assertJsonPath('camera', 4);

        config(['smart_freezer.clips.vends' => [], 'smart_freezer.clips.camera' => 0]);
        $get()->assertOk()->assertJsonPath('camera', null);
    }

    public function test_unsigned_or_wrongly_signed_uploads_are_refused(): void
    {
        $this->upload($this->mp4(), key: 'WRONGKEY00000000')->assertStatus(401);

        $path = '/api/v1/vends/50001/clips/'.self::REF.'/5';
        $this->call('POST', $path, [], [], [], ['CONTENT_TYPE' => 'video/mp4', 'HTTP_ACCEPT' => 'application/json'], $this->mp4())
            ->assertStatus(401);
        $this->assertSame(0, SmartFreezerClip::count());
    }

    public function test_bad_clips_are_refused_for_good(): void
    {
        $this->upload('not a video at all, just text')->assertStatus(422)->assertJsonPath('error', 'not an mp4');
        $this->upload($this->mp4(), 'SF-2009-1791539003-2')->assertStatus(422);

        config(['smart_freezer.clips.max_bytes' => 1000]);
        $this->upload($this->mp4())->assertStatus(422);

        $this->vend->forceFill(['machine_type' => 'vending'])->save();
        config(['smart_freezer.clips.max_bytes' => 40 * 1024 * 1024]);
        $this->upload($this->mp4())->assertStatus(422)->assertJsonPath('error', 'not a smart freezer');

        $this->assertSame(0, SmartFreezerClip::count());
        $this->assertSame([], Storage::disk()->allFiles());
    }

    public function test_the_clip_plays_only_for_a_permitted_viewer_of_that_operator(): void
    {
        $this->upload($this->mp4())->assertCreated();
        $clip = SmartFreezerClip::sole();

        // A disk that can sign links (Spaces in prod) sends the browser to a short-lived one, so
        // seeking is served by the storage itself.
        $location = $this->actingAs($this->viewer())->get("/freezer-clips/{$clip->id}")
            ->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString(self::REF.'-cam5.mp4', (string) $location);
        $this->assertStringContainsString('expiration=', (string) $location, 'a link that expires');

        $other = Operator::withoutGlobalScopes()->create(['code' => 'OTHER', 'name' => 'Other', 'is_active' => true]);
        $stranger = User::factory()->create(['operator_id' => $other->id]);
        $stranger->givePermissionTo('read ai-recognition');
        $this->actingAs($stranger)->get("/freezer-clips/{$clip->id}")->assertNotFound();

        $noPermission = User::factory()->create(['operator_id' => $this->operator->id]);
        $this->actingAs($noPermission)->get("/freezer-clips/{$clip->id}")->assertForbidden();
    }

    public function test_sales_transactions_and_ai_recognition_carry_the_play_link(): void
    {
        $this->upload($this->mp4(), headers: ['X-Clip-Duration-Ms' => '28000'])->assertCreated();
        $clip = SmartFreezerClip::sole();
        $base = ['vend_id' => $this->vend->id, 'operator_id' => $this->operator->id, 'transaction_datetime' => now(),
            'amount' => 430, 'qty' => 1, 'vend_channel_code' => 41, 'vend_channel_id' => 0, 'gst_vat_rate' => 0,
            'interface_type' => 0, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED];
        VendTransaction::create($base + ['order_id' => 'WITH', 'vend_transaction_json' => ['Type' => 'TRADE', 'SFREF' => self::REF]]);
        VendTransaction::create($base + ['order_id' => 'WITHOUT', 'vend_transaction_json' => ['Type' => 'TRADE', 'SFREF' => 'SF-50001-1791539999-3']]);
        SmartFreezerRecognition::create(['vend_id' => $this->vend->id, 'trade_id' => self::REF, 'session_ref' => self::REF, 'status' => 'completed']);

        $user = $this->viewer();
        $rows = [];
        $this->actingAs($user)->get('/vends/transactions?'.http_build_query([
            'date_from' => now()->subDay()->toDateTimeString(), 'date_to' => now()->addDay()->toDateTimeString(),
            'operators' => [$this->operator->id],
        ]))->assertOk()->assertInertia(function (AssertableInertia $page) use (&$rows) {
            foreach ($page->toArray()['props']['vendTransactions']['data'] as $row) {
                $rows[$row['order_id']] = $row;
            }
        });
        $this->assertSame([[
            'id' => $clip->id, 'camera_id' => 5, 'seconds' => 28, 'bytes' => 2048,
            'url' => route('freezer-clips.show', $clip->id),
        ]], $rows['WITH']['door_clips']);
        $this->assertNull($rows['WITHOUT']['door_clips']);

        $this->actingAs($user)->get('/ai-recognition')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('recognitions.data.0.clips.0.id', $clip->id)
                ->where('recognitions.data.0.clips.0.camera_id', 5));
    }
}
