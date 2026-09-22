<?php

namespace Tests\Feature;

use App\Jobs\CreateVendData;
use App\Jobs\Vend\IncrementVendDailyStat;
use App\Models\Vend;
use App\Services\VendDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Ingest of the UIHEALTH frame — the only signal mark1 has about the SCREEN.
 *
 * Every other liveness channel (VENDER, ACBSTATUS, the P polls) is relayed off
 * the serial link by the APK's ThreadForBrd and keeps arriving unbroken while
 * the UI is completely dead — machine 2844 on 2026-09-21 reported normally right
 * up to the manual power-cycle that fixed it. So these frames are load-bearing:
 * if they stop being counted, a frozen fleet looks healthy again.
 *
 * What is pinned here: which events become counters (and which deliberately do
 * not), and that the diagnostic payload is still handed to vend_data, because
 * the stack trace is the whole point — a reboot destroys it and nothing else
 * records it server-side.
 *
 * NOTE on the assertions: the counter is raised with dispatchSync, which
 * Queue::fake() records instead of executing, so these assert the dispatch and
 * its arguments. That the increment itself is atomic and accumulates is
 * IncrementVendDailyStat's own contract.
 */
class VendUiHealthIngestTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        // vend_data storage is behind an env kill switch (LOG_TO_VEND_DATA, off by
        // default in tests). Turn it on here: "the frame is kept" is the contract
        // under test, and prod runs with it enabled.
        config(['app.log_to_vend_data' => true]);
        $this->vend = Vend::forceCreate([
            'code' => 2844,
            'apk_ver_json' => ['apkver' => '306', 'deviceType' => 'ZC-328'],
        ]);
    }

    private function receive(array $payload): void
    {
        $service = new VendDataService;
        $message = 'f=4&t=5&m=2844&g=20&p='.base64_encode(json_encode($payload));
        $std = $service->standardizedVendData($message, 'mqtt');
        $decoded = $service->decodeVendData($std);
        $service->processVendData($std, $decoded, '143.198.221.235', 'mqtt');
    }

    private function frame(string $event, array $extra = []): array
    {
        return array_merge([
            'Type' => 'UIHEALTH',
            'vid' => 2844,
            'event' => $event,
            'ms' => 12000,
            'apkver' => '306',
        ], $extra);
    }

    private function assertCounted(string $metric): void
    {
        Queue::assertPushed(IncrementVendDailyStat::class,
            fn (IncrementVendDailyStat $job) => $job->metric === $metric
                && $job->vendId === $this->vend->id
                && $job->vendCode === '2844');
    }

    private function assertNotCounted(string $metric): void
    {
        Queue::assertNotPushed(IncrementVendDailyStat::class,
            fn (IncrementVendDailyStat $job) => $job->metric === $metric);
    }

    public function test_a_looper_stall_is_counted(): void
    {
        $this->receive($this->frame('looper_stall', [
            'detail' => 'at android.os.MessageQueue.nativePollOnce(Native Method) |',
        ]));

        $this->assertCounted('ui_looper_stall');
    }

    public function test_a_dead_draw_pipeline_is_counted_separately_from_a_stall(): void
    {
        $this->receive($this->frame('no_frames', ['detail' => 'looper healthy, 0 frames']));

        $this->assertCounted('ui_no_frames');
        $this->assertNotCounted('ui_looper_stall');
    }

    public function test_probe_pass_and_fail_are_counted_on_their_own_metrics(): void
    {
        $this->receive($this->frame('probe_pass', ['ms' => 830]));
        $this->receive($this->frame('probe_fail', ['stage' => 'open', 'ms' => 5010]));

        $this->assertCounted('ui_probe_pass');
        $this->assertCounted('ui_probe_fail');
    }

    public function test_every_event_is_counted_once_not_per_machine_poll(): void
    {
        $this->receive($this->frame('looper_stall'));
        $this->receive($this->frame('looper_stall'));
        $this->receive($this->frame('looper_stall'));

        Queue::assertPushed(IncrementVendDailyStat::class, 3);
    }

    /**
     * A recovery pairs with a stall that has already been counted, and an abort
     * means a customer walked up mid-probe — neither is a fault, and counting
     * them would inflate the apparent failure rate of every machine that
     * recovers on its own.
     */
    public function test_recovery_and_abort_are_stored_but_not_counted(): void
    {
        $this->receive($this->frame('looper_recovered', ['ms' => 41000]));
        $this->receive($this->frame('probe_abort', ['stage' => 'open']));

        Queue::assertNotPushed(IncrementVendDailyStat::class);
        // Still kept for diagnosis: one vend_data row per frame.
        Queue::assertPushed(CreateVendData::class, 2);
    }

    public function test_an_unknown_future_event_never_creates_a_junk_metric(): void
    {
        $this->receive($this->frame('something_we_add_in_307'));

        Queue::assertNotPushed(IncrementVendDailyStat::class);
    }

    /**
     * The counters only say WHICH machine. The stack frames in the payload are
     * the diagnosis, and vend_data is the only place they survive.
     */
    public function test_the_diagnostic_payload_is_kept_verbatim(): void
    {
        $stack = 'at android.os.MessageQueue.nativePollOnce(Native Method) | at com.cvvenderRouter.Main2Activity.x(Main2Activity.java:4242) |';

        $this->receive($this->frame('looper_stall', ['detail' => $stack, 'ms' => 63000]));

        Queue::assertPushed(CreateVendData::class, function (CreateVendData $job) use ($stack) {
            $processed = (fn () => $this->processedInput)->call($job);

            return ($processed['Type'] ?? null) === 'UIHEALTH'
                && ($processed['detail'] ?? null) === $stack
                && ($processed['ms'] ?? null) === 63000;
        });
    }
}
